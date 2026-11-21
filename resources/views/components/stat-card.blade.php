@props(['title', 'value', 'icon', 'color' => 'primary', 'footer' => null])
<div class="small-box bg-{{ $color }}">
    <div class="inner">
        <h3>{{ $value }}</h3>
        <p>{{ $title }}</p>
    </div>
    <div class="icon"><i class="{{ $icon }}"></i></div>
    @if($footer)
        <span class="small-box-footer">{{ $footer }}</span>
    @else
        <span class="small-box-footer">&nbsp;</span>
    @endif
</div>
