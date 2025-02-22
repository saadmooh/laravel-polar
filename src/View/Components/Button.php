<?php

namespace Danestves\LaravelPolar\View\Components;

use Illuminate\View\Component;

class Button extends Component
{
    public function __construct(
        public string $href,
        public string $theme = 'light'
    ) {}

    public function render()
    {
        return view('polar::components.button');
    }
}
