@if ($paginator->hasPages())
<nav class="pager" role="navigation" aria-label="Pagination">
    <span class="pager-count">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
    <span class="pager-links">
        @if ($paginator->onFirstPage())
            <span class="pg is-off" aria-disabled="true">&larr; Prev</span>
        @else
            <a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; Prev</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pg is-off">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pg is-on" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pg" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rarr;</a>
        @else
            <span class="pg is-off" aria-disabled="true">Next &rarr;</span>
        @endif
    </span>
</nav>
@endif
