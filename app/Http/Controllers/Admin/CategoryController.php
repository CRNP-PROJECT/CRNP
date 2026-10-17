<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Catalog\Actions\CreateCategory;
use App\Catalog\Actions\DeleteCategory;
use App\Catalog\Actions\UpdateCategory;
use App\Catalog\Catalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

/**
 * The admin's category surface: create, rename/reorder, delete.
 *
 * A delete is refused while a product still references the category; the action
 * throws and the controller turns that into a form error rather than orphaning a
 * product. Every write evicts the catalog cache.
 */
final class CategoryController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => $this->catalog->categories(),
        ]);
    }

    public function store(SaveCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        /** @var array{name: string, order: int} $validated */
        $validated = $request->validated();

        $createCategory($validated['name'], (int) $validated['order']);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category added.');
    }

    public function update(
        SaveCategoryRequest $request,
        string $category,
        UpdateCategory $updateCategory,
    ): RedirectResponse {
        /** @var array{name: string, order: int} $validated */
        $validated = $request->validated();

        $updateCategory($category, $validated['name'], (int) $validated['order']);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category updated.');
    }

    public function destroy(string $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        try {
            $deleteCategory($category);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.categories.index')
                ->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category deleted.');
    }
}
