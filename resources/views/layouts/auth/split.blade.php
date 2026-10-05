<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <main class="auth-split">
            <aside class="auth-split__aside">
                <a class="auth-brand auth-brand--inverse" href="{{ route('home') }}" wire:navigate>
                    <x-app-logo />
                </a>
                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp
                <blockquote class="auth-quote">
                    <p>&ldquo;{{ trim($message) }}&rdquo;</p>
                    <footer>{{ trim($author) }}</footer>
                </blockquote>
            </aside>
            <section class="auth-split__content">
                {{ $slot }}
            </section>
        </main>
        @persist('toast-region')
            <x-ui.toast-region />
        @endpersist
    </body>
</html>
