<script>
    toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 6000 };
    @foreach (['success', 'warning', 'error', 'info'] as $type)
        @if (session($type))
            toastr.{{ $type }}(@json(session($type)));
        @endif
    @endforeach
</script>
