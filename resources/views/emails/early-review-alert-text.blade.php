إنذار تقييمات مشبوهة

القناة: {{ $review->provider->name }}
@foreach ($labels as $label)
- {{ $label }}
@endforeach

آخر تقييم ({{ $review->rating }} من 5):
{{ \Illuminate\Support\Str::limit($review->body, 300) }}

التقييم منشور. راجعه واحذفه أو ارفضه إذا كان مزيفاً.
{{ $adminUrl }}
