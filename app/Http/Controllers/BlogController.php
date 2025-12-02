<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Inertia\Inertia;

class BlogController extends Controller
{
    public function index()
    {
        $posts = BlogPost::with('author:id,name')
            ->published()
            ->latest()
            ->paginate(9);

        $categories = BlogPost::getCategories();

        return Inertia::render('Blog', [
            'posts' => $posts,
            'categories' => $categories,
        ]);
    }

    public function show(BlogPost $post)
    {
        // Only show published posts
        if ($post->status !== 'published') {
            abort(404);
        }

        $post->incrementViews();
        $post->load('author:id,name');

        $relatedPosts = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->latest()
            ->limit(3)
            ->get();

        return Inertia::render('BlogPost', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }
}

