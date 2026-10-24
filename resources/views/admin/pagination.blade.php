@if ($paginator->hasPages())
    @if (! $paginator->onFirstPage())
        <a class="btn btn-secondary btn-small" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif
    <span class="muted num" style="align-self:center">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())
        <a class="btn btn-secondary btn-small" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @endif
@endif
