@if ($paginator->hasPages())
    <nav class="pagination">
        @if (! $paginator->onFirstPage())<a class="btn small" href="{{ $paginator->previousPageUrl() }}">← Previous</a>@endif
        <span class="small muted" style="align-self:center;">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())<a class="btn small" href="{{ $paginator->nextPageUrl() }}">Next →</a>@endif
    </nav>
@endif
