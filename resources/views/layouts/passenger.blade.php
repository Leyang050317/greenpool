@include('passenger.booking.layout', [
    'slotContent' => trim($__env->yieldContent('content')) === '' ? null : new Illuminate\Support\HtmlString($__env->yieldContent('content')),
    'pageTitle' => trim($__env->yieldContent('pageTitle', 'Dashboard')),
])
