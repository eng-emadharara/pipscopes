<x-email.layout>
    <h1 style="margin:0 0 12px;font-size:20px;">إنذار تقييمات مشبوهة</h1>
    <p style="margin:0 0 12px;">القناة: <strong>{{ $review->provider->name }}</strong></p>
    <ul style="margin:0 0 16px;padding:0 20px;">
        @foreach ($labels as $label)
            <li>{{ $label }}</li>
        @endforeach
    </ul>
    <p style="margin:0 0 6px;color:#555;">آخر تقييم ({{ $review->rating }} من 5):</p>
    <p dir="auto" style="margin:0 0 20px;padding:12px;background:#f6f6f6;border-radius:10px;">{{ \Illuminate\Support\Str::limit($review->body, 300) }}</p>
    <p style="margin:0 0 20px;color:#555;">التقييم منشور. راجعه واحذفه أو ارفضه إذا كان مزيفاً.</p>
    <a href="{{ $adminUrl }}" style="display:inline-block;background:#00b67a;color:#fff;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:999px;">مراجعة التقييمات</a>
</x-email.layout>
