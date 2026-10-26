<x-layouts.app :title="__('هل هذا الحساب حقيقي؟')">
    <section class="mx-auto mt-10 max-w-4xl px-4">
        @include('home._account-check', ['account' => $account, 'result' => $result])
    </section>
</x-layouts.app>
