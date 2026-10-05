@props([
    'title',
    'description',
])

<div class="auth-header">
    <x-ui.heading level="1" size="xl">{{ $title }}</x-ui.heading>
    <x-ui.subheading>{{ $description }}</x-ui.subheading>
</div>
