@props(['href', 'theme' => 'light'])

<a href="{!! $href !!}" data-polar-checkout data-polar-checkout-theme="{{ $theme }}" {{ $attributes }}>
    {{ $slot }}
</a>
