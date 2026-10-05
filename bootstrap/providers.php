<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    class_exists(Modules\WhatsApp\WhatsAppServiceProvider::class) ? Modules\WhatsApp\WhatsAppServiceProvider::class : null,
];
