<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->latest('published_at')
            ->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Daftar artikel berhasil diambil',
            'data' => [
                'articles' => $articles->items(),
                'meta' => [
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                    'per_page' => $articles->perPage(),
                    'total' => $articles->total(),
                ],
            ],
        ]);
    }

    public function show(Article $article)
    {
        return response()->json([
            'status' => true,
            'message' => 'Detail artikel berhasil diambil',
            'data' => [
                'article' => $article
            ],
        ]);
    }
} 