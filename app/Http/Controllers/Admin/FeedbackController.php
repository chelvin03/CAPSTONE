<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['rating' => ['nullable', 'integer', 'between:1,5']]);
        $feedback = Feedback::with('reservation')->when($filters['rating'] ?? null, fn ($q, $rating) => $q->where('rating', $rating))
            ->latest()->paginate(15)->withQueryString();
        return view('admin.feedback.index', ['feedback' => $feedback, 'total' => Feedback::count(), 'average' => Feedback::avg('rating')]);
    }
}
