<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScamReportResource\Pages;
use App\Models\Impersonator;
use App\Models\ScamCase;
use App\Models\ScamReport;
use App\Support\NotificationPrefs;
use App\Support\ScamHelp;
use App\Support\ScamReportContact;
use App\Support\Site;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Scam reports from members ("انتصب عليّ؟"): check the proofs, confirm into the case, or reject. */
class ScamReportResource extends Resource
{
    protected static ?string $model = ScamReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'الطلبات والتقييمات';

    protected static ?string $modelLabel = 'بلاغ نصب';

    protected static ?string $pluralModelLabel = 'بلاغات النصب';

    public static function getNavigationBadge(): ?string
    {
        return (string) ScamReport::where('status', ScamReport::STATUS_PENDING)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('البلاغ')->columns(3)->schema([
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => ScamReport::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'confirmed' => 'success'][$state] ?? 'gray'),
                Infolists\Components\TextEntry::make('scam_type')->label('النوع')
                    ->formatStateUsing(fn ($state) => ScamHelp::types()[$state][1] ?? $state),
                Infolists\Components\TextEntry::make('payment_method')->label('الدفع')
                    ->formatStateUsing(fn ($state) => ScamHelp::payments()[$state] ?? $state),
                Infolists\Components\TextEntry::make('amount')->label('المبلغ')
                    ->formatStateUsing(fn ($state, ScamReport $record) => $record->amountLabel().($record->amount_usd !== null ? ' (≈ $'.number_format((float) $record->amount_usd).')' : ''))
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('paid_at')->label('تاريخ الدفع')->date('Y-m-d')->placeholder('—'),
                Infolists\Components\TextEntry::make('created_at')->label('أُرسل')->since(),
                Infolists\Components\TextEntry::make('account')->label('الحساب')->copyable()->placeholder('—')->extraAttributes(['dir' => 'ltr']),
                Infolists\Components\TextEntry::make('wallet')->label('المحفظة')->copyable()->placeholder('—')->extraAttributes(['dir' => 'ltr']),
                Infolists\Components\TextEntry::make('broker')->label('الوسيط')->placeholder('—'),
                Infolists\Components\TextEntry::make('story')->label('ماذا حدث')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                Infolists\Components\TextEntry::make('proofs')->label('الإثباتات (خاصة)')->columnSpanFull()
                    ->state(fn (ScamReport $record) => self::proofsHtml($record)),
            ]),
            Infolists\Components\Section::make('المُبلِّغ')->columns(3)
                ->headerActions(self::contactActions(Infolists\Components\Actions\Action::class))
                ->schema([
                    Infolists\Components\TextEntry::make('user.name')->label('العضو')
                        ->url(fn (ScamReport $record) => $record->user ? UserResource::getUrl('index', ['tableSearch' => $record->user->email ?: $record->user->name]) : null),
                    Infolists\Components\TextEntry::make('user.email')->label('البريد')->placeholder('—')->copyable()
                        ->url(fn (ScamReport $record) => ($email = ScamReportContact::email($record->user)) ? 'mailto:'.$email : null)
                        ->extraAttributes(['dir' => 'ltr']),
                    Infolists\Components\TextEntry::make('telegram')->label('تيليجرام')->placeholder('لم يدخل عبر تيليجرام')
                        ->state(fn (ScamReport $record) => ($u = ScamReportContact::telegramUsername($record->user)) ? '@'.$u
                            : ($record->user && NotificationPrefs::telegramChatId($record->user) ? 'بدون اسم مستخدم (يمكن مراسلته عبر البوت)' : null))
                        ->url(fn (ScamReport $record) => ($u = ScamReportContact::telegramUsername($record->user)) ? 'https://t.me/'.$u : null, true)
                        ->color(fn (ScamReport $record) => $record->user?->telegram_blocked_at ? 'danger' : null)
                        ->helperText(fn (ScamReport $record) => $record->user?->telegram_blocked_at ? 'أوقف البوت، لا تصله رسائلنا' : null),
                    Infolists\Components\TextEntry::make('contact')->label('تواصل إضافي (كتبه في البلاغ)')->placeholder('—')->copyable()
                        ->url(fn (ScamReport $record) => ScamReportContact::contactUrl($record->contact), true)
                        ->extraAttributes(['dir' => 'auto']),
                    Infolists\Components\TextEntry::make('user.created_at')->label('عضو منذ')->date('Y-m-d'),
                    Infolists\Components\TextEntry::make('user_reports')->label('بلاغاته')
                        ->state(function (ScamReport $record) {
                            $all = ScamReport::where('user_id', $record->user_id);
                            $confirmed = (clone $all)->where('status', ScamReport::STATUS_CONFIRMED)->count();

                            return $all->count().' بلاغ'.($confirmed ? " ({$confirmed} مؤكد)" : '');
                        }),
                ]),
            Infolists\Components\Section::make('الموافقات والملف')->columns(3)->schema([
                Infolists\Components\IconEntry::make('consent_share')->label('يوافق على المشاركة مع الجهات')->boolean(),
                Infolists\Components\IconEntry::make('consent_contact')->label('يوافق على التواصل لتحرك جماعي')->boolean(),
                Infolists\Components\TextEntry::make('scamCase.target_display')->label('الملف')->placeholder('—')
                    ->url(fn (ScamReport $record) => $record->scamCase ? ScamCaseResource::getUrl('edit', ['record' => $record->scamCase]) : null),
                Infolists\Components\TextEntry::make('related')->label('بلاغات أخرى بنفس المحفظة')->columnSpanFull()
                    ->state(fn (ScamReport $record) => self::sameWallet($record))->placeholder('—'),
                Infolists\Components\TextEntry::make('admin_note')->label('ملاحظة للمُبلِّغ')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * Reaching the member: their Telegram chat, a message from our bot, and an email (replies come
     * to the admin who sent it). $class is the Action class of where they're shown.
     *
     * @param  class-string  $class
     */
    public static function contactActions(string $class): array
    {
        $notify = fn (?string $error) => $error
            ? Notification::make()->title($error)->danger()->send()
            : Notification::make()->title('أُرسلت الرسالة')->success()->send();

        return [
            $class::make('openTelegram')->label('محادثة تيليجرام')->icon('heroicon-o-chat-bubble-left-right')->color('info')
                ->visible(fn (ScamReport $record) => ScamReportContact::telegramUsername($record->user) !== null)
                ->url(fn (ScamReport $record) => 'https://t.me/'.ScamReportContact::telegramUsername($record->user), true),
            $class::make('messageBot')->label('رسالة عبر البوت')->icon('heroicon-o-paper-airplane')->color('info')
                ->visible(fn (ScamReport $record) => ScamReportContact::canMessageOnTelegram($record->user))
                ->modalHeading('رسالة للعضو من البوت')
                ->modalDescription('تصله من بوت الموقع، مع زر «بلاغاتي». لا يستطيع الرد عليها عبر البوت، فاطلب منه وسيلة تواصل إن احتجت رده.')
                ->form([
                    Forms\Components\Textarea::make('text')->label('الرسالة')->required()->rows(5)->maxLength(3500)
                        ->placeholder('مثال: نحتاج صورة إيصال الدفع كاملة حتى نؤكد بلاغك.'),
                ])
                ->modalSubmitActionLabel('إرسال')
                ->action(fn (ScamReport $record, array $data) => $notify(ScamReportContact::sendTelegram($record, $data['text']))),
            $class::make('sendEmail')->label('بريد إلكتروني')->icon('heroicon-o-envelope')->color('gray')
                ->visible(fn (ScamReport $record) => ScamReportContact::email($record->user) !== null)
                ->modalHeading(fn (ScamReport $record) => 'بريد إلى '.ScamReportContact::email($record->user))
                ->modalDescription('يُرسل من بريد الموقع، وإذا ردّ العضو يصلك الرد على بريدك.')
                ->form([
                    Forms\Components\TextInput::make('subject')->label('العنوان')->required()->maxLength(150)
                        ->default(fn () => 'بخصوص بلاغك على '.Site::name('ar')),
                    Forms\Components\Textarea::make('body')->label('الرسالة')->required()->rows(6)->maxLength(5000),
                ])
                ->modalSubmitActionLabel('إرسال')
                ->action(fn (ScamReport $record, array $data) => $notify(ScamReportContact::sendEmail($record, $data['subject'], $data['body'], auth()->user()?->email))),
        ];
    }

    protected static function proofsHtml(ScamReport $record): HtmlString
    {
        if (! $record->proofs) {
            return new HtmlString('<span class="text-gray-500">لا توجد إثباتات</span>');
        }

        return new HtmlString('<div style="display:flex;flex-wrap:wrap;gap:8px">'.collect($record->proofs)->keys()->map(function ($i) use ($record) {
            $url = e(route('scam-reports.proof', [$record, $i]));

            return "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\"><img src=\"{$url}\" alt=\"\" style=\"height:140px;border-radius:8px;border:1px solid #e5e7eb\"></a>";
        })->implode('').'</div>');
    }

    protected static function sameWallet(ScamReport $record): ?string
    {
        if (! $record->wallet) {
            return null;
        }

        $count = ScamReport::whereKeyNot($record->id)->where('wallet', $record->wallet)->count();

        return $count ? $count.' بلاغ (قد ترغب بنقلها لملف واحد)' : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('scamCase.target_display')->label('الجهة')->searchable()->wrap()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->description(fn (ScamReport $r) => $r->scamCase?->targetLabel()),
                Tables\Columns\TextColumn::make('scam_type')->label('النوع')->wrap()
                    ->formatStateUsing(fn ($state) => ScamHelp::types()[$state][1] ?? $state),
                Tables\Columns\TextColumn::make('amount_usd')->label('المبلغ ≈ $')->numeric()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('proofs')->label('إثباتات')
                    ->state(fn (ScamReport $r) => count($r->proofs ?? [])),
                Tables\Columns\TextColumn::make('user.name')->label('العضو')->searchable(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => ScamReport::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'confirmed' => 'success'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('created_at')->label('أُرسل')->since()->sortable(),
            ])
            ->actions(static::recordActions(table: true));
    }

    /** The decisions, for both the table rows and the report's own page */
    public static function recordActions(bool $table = false): array
    {
        $make = fn (string $name) => ($table ? Tables\Actions\Action::class : \Filament\Actions\Action::class)::make($name);

        return array_values(array_filter([
            $make('confirm')->label('تأكيد وإضافة للملف')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (ScamReport $r) => $r->status !== ScamReport::STATUS_CONFIRMED)
                ->requiresConfirmation()
                ->modalDescription('يُحسب البلاغ في ملف الجهة، ويصل للعضو إشعار بأنه انضم للملف.')
                ->action(fn (ScamReport $r) => $r->update(['status' => ScamReport::STATUS_CONFIRMED])),
            $make('reject')->label('رفض')->icon('heroicon-o-x-mark')->color('gray')
                ->visible(fn (ScamReport $r) => $r->status !== ScamReport::STATUS_REJECTED)
                ->form([
                    Forms\Components\Textarea::make('admin_note')->label('ملاحظة للعضو (اختياري)')
                        ->placeholder('مثال: الإثباتات لا تظهر اسم الحساب أو عملية الدفع')->rows(2),
                ])
                ->action(fn (ScamReport $r, array $data) => $r->update(['status' => ScamReport::STATUS_REJECTED, 'admin_note' => $data['admin_note'] ?? null])),
            $make('move')->label('نقل لملف آخر')->icon('heroicon-o-folder-arrow-down')->color('gray')
                ->form([
                    Forms\Components\Select::make('scam_case_id')->label('الملف')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search) => ScamCase::where('target_display', 'like', "%{$search}%")->limit(30)->pluck('target_display', 'id'))
                        ->getOptionLabelUsing(fn ($value) => ScamCase::find($value)?->target_display),
                ])
                ->action(fn (ScamReport $r, array $data) => $r->update(['scam_case_id' => $data['scam_case_id']])),
            $make('impersonator')->label('أضف الحساب للمنتحلين')->icon('heroicon-o-shield-exclamation')->color('danger')
                ->visible(fn (ScamReport $r) => $r->scam_type === 'impersonator' && $r->scamCase && ! in_array($r->scamCase->target_type, ['wallet', 'broker'], true))
                ->form([
                    Forms\Components\Select::make('provider_id')->label('القناة التي انتحل اسمها')
                        ->options(fn () => \App\Models\Provider::orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                ])
                ->action(function (ScamReport $r, array $data) {
                    Impersonator::firstOrCreate(
                        ['provider_id' => $data['provider_id'], 'account_key' => $r->scamCase->target_key],
                        ['platform' => $r->scamCase->target_type, 'handle' => $r->account ?: $r->scamCase->target_display, 'status' => 'confirmed'],
                    );
                    Notification::make()->title('أُضيف الحساب لقائمة المنتحلين المؤكدين')->success()->send();
                }),
            $table ? Tables\Actions\ActionGroup::make(self::contactActions(Tables\Actions\Action::class))->label('تواصل')->icon('heroicon-o-chat-bubble-left-ellipsis')->button()->color('gray') : null,
            $table ? Tables\Actions\ViewAction::make() : null,
        ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScamReports::route('/'),
            'view' => Pages\ViewScamReport::route('/{record}'),
        ];
    }
}
