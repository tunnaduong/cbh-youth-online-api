<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\TopicView;
use App\Models\TopicVote;
use App\Models\TopicComment;
use Illuminate\Http\Request;
use App\Models\TopicCommentVote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TopicsController extends Controller
{
    // GET /topics – Get list of topics
    public function index()
    {
        // Fetch all topics along with their associated user details
        $topics = Topic::with('user')->get();

        // Transform the response to include required user details
        $formattedTopics = $topics->map(function ($topic) {
            return [
                'id' => $topic->id,
                'user_id' => $topic->user_id,
                'title' => $topic->title,
                'description' => $topic->description,
                'created_at' => $topic->created_at,
                'updated_at' => $topic->updated_at,
                'author' => [
                    'username' => $topic->user->username,
                    'email' => $topic->user->email,
                    'profile_name' => $topic->user->profile->profile_name ?? null, // Include profile_name
                ],
            ];
        });

        return response()->json($formattedTopics);
    }

    // POST /topics – Create a new topic
    public function store(Request $request)
    {
        // Validate the request data
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        // Create the topic and associate it with the logged-in user
        $topic = Topic::create([
            'user_id' => Auth::id(), // Assuming you're using Laravel's Auth system
            'title' => $request->title,
            'description' => $request->description,
        ]);

        return response()->json($topic, 201);
    }

    // Get views for a topic
    public function getViews($topicId)
    {
        $views = TopicView::where('topic_id', $topicId)->get();
        return response()->json($views);
    }

    // Register a view for a topic
    public function registerView(Request $request, $topicId)
    {
        // Check if the topic exists
        $topic = Topic::findOrFail($topicId);
        $userId = auth()->check() ? auth()->id() : null;

        // Register the view
        TopicView::create([
            'topic_id' => $topic->id,
            'user_id' => $userId,
        ]);

        return response()->json(['message' => 'View registered successfully'], 201);
    }



    // Get votes for a topic
    public function getVotes($topicId)
    {
        $votes = TopicVote::where('topic_id', $topicId)->get();
        return response()->json($votes);
    }

    // Register a vote for a topic
    public function registerVote(Request $request, $topicId)
    {
        $request->validate([
            'vote_value' => 'required|integer|in:1,-1', // true for upvote, false for downvote
        ]);

        TopicVote::updateOrCreate(
            [
                'topic_id' => $topicId,
                'user_id' => auth()->id(),
            ],
            [
                'vote_value' => $request->input('vote_value'),
            ]
        );

        return response()->json(['message' => 'Vote registered'], 201);
    }

    // Get comments for a topic
    public function getComments($topicId)
    {
        $comments = TopicComment::where('topic_id', $topicId)->with('user')->get();
        return response()->json($comments);
    }

    // Add a comment to a topic
    public function addComment(Request $request, $topicId)
    {
        $request->validate([
            'comment' => 'required|string',
        ]);

        $comment = TopicComment::create([
            'topic_id' => $topicId,
            'user_id' => auth()->id(),
            'comment' => $request->comment,
        ]);

        return response()->json($comment, 201);
    }

    // Vote on a comment
    public function voteOnComment(Request $request, $commentId)
    {
        $request->validate([
            'vote' => 'required|boolean', // true for upvote, false for downvote
        ]);

        TopicCommentVote::updateOrCreate(
            [
                'comment_id' => $commentId,
                'user_id' => auth()->id(),
            ],
            [
                'vote' => $request->vote,
            ]
        );

        return response()->json(['message' => 'Vote registered on comment'], 201);
    }

    // Method to get votes for a specific comment
    public function getVotesForComment($id)
    {
        // Use findOrFail to retrieve the comment by its ID
        $comment = TopicComment::findOrFail($id);

        // Fetch votes for the comment
        $votes = TopicCommentVote::where('comment_id', $comment->id)->get();

        return response()->json([
            'comment_id' => $comment->id,
            'votes' => $votes,
        ]);
    }
}
