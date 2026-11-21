@props(['title', 'breadcrumb' => []])
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0">{{ $title }}</h1></div>
    <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
            @foreach($breadcrumb as $item)
                @if($loop->last)
                    <li class="breadcrumb-item active">{{ $item['label'] }}</li>
                @else
                    <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                @endif
            @endforeach
        </ol>
    </div>
</div>
