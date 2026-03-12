<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationViewTest extends TestCase
{
    public function test_tailwind_pagination_view_renders_compact_links(): void
    {
        $paginator = new LengthAwarePaginator(
            items: collect(range(121, 140)),
            total: 2680,
            perPage: 20,
            currentPage: 7,
            options: [
                'path' => '/insights',
                'pageName' => 'page',
                'query' => ['search' => 'AL12', 'sort' => 'transactions_desc'],
            ],
        );

        $view = $this->view('vendor.pagination.tailwind', [
            'paginator' => $paginator,
            'elements' => [],
        ]);

        $view->assertSee('Showing');
        $view->assertSee('121');
        $view->assertSee('140');
        $view->assertSee('2680');
        $view->assertSee('insights');
        $view->assertSee('Previous');
        $view->assertSee('Next');
        $view->assertSee('/insights?search=AL12&amp;sort=transactions_desc&amp;page=1', false);
        $view->assertSee('/insights?search=AL12&amp;sort=transactions_desc&amp;page=6', false);
        $view->assertSee('/insights?search=AL12&amp;sort=transactions_desc&amp;page=8', false);
        $view->assertSee('/insights?search=AL12&amp;sort=transactions_desc&amp;page=134', false);
        $view->assertSee('…');
        $view->assertSee('search=AL12', false);
        $view->assertSee('sort=transactions_desc', false);
    }
}
