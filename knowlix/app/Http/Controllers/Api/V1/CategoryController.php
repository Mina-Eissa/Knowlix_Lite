<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return Category::with('children')
            ->whereNull('parent_id')
            ->get();
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        $this->authorize('view', $category);

        return $category->load('children');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return $category;
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(['message' => 'Category deleted'], 200);
    }
}
