<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\TopicView;
use App\Models\TopicVote;
use App\Models\UserContent;
use App\Models\TopicComment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\UserSavedTopic;
use Illuminate\Support\Carbon;
use App\Models\TopicCommentVote;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class TopicsController extends Controller
{
    // GET /topics – Get list of topics
    public function index()
    {
        // Fetch topics from the database
        $topics = Topic::withCount(['views', 'comments'])  // Fetch comments count for each topic
            ->with('user')  // Assuming 'author' is a relation to the User model
            ->get()
            ->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'content' => nl2br(e($topic->description)),
                    'author' => [
                        'id' => $topic->user->id,
                        'username' => $topic->user->username,
                        'email' => $topic->user->email,
                        'profile_name' => $topic->user->profile->profile_name ?? null,
                    ],
                    'time' => Carbon::parse($topic->created_at)->diffForHumans(),  // Time in human-readable format
                    'comments' => $this->roundToNearestFive($topic->comments_count) . '+', // Comment count in '05+' format
                    'views' => $topic->views_count, // Fallback to 0 if 'views' is null
                    'votes' => $topic->votes ?? 0, // Fallback to 0 if 'votes' is null
                ];
            });

        // Return the topics as a JSON response test
        return response()->json($topics);
    }

    private function roundToNearestFive($count)
    {
        if ($count <= 5) {
            // If count is less than or equal to 5, format it with leading zero
            return str_pad($count, 2, '0', STR_PAD_LEFT);
        } else {
            // Round down to the nearest multiple of 5 and pad to 2 digits
            return str_pad(floor($count / 5) * 5, 2, '0', STR_PAD_LEFT);
        }
    }


    // POST /topics – Create a new topic
    public function store(Request $request)
    {
        // Validate the request data
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'subforum_id' => 'nullable|exists:cyo_forum_subforums,id', // Kiểm tra subforum_id
            'user_content_id' => 'nullable|exists:cyo_cdn_user_content,id',
        ]);

        // Fetch the image URL if user_content_id is provided
        $imageUrl = null;
        if (isset($request->user_content_id)) {
            $userContent = UserContent::find($request->user_content_id);
            if ($userContent) {
                $imageUrl = $userContent->file_path; // Assuming file_path contains the URL
            }
        }

        $topic = Topic::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'subforum_id' => $request->subforum_id, // Gán giá trị cho subforum_id
            'image_url' => $imageUrl,
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
        $comments = TopicComment::with(['user', 'user.profile'])
            ->where('topic_id', $topicId)
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'topic_id' => $comment->topic_id,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'author' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'email' => $comment->user->email,
                        'profile_name' => $comment->user->profile->profile_name ?? null, // Handle case where profile might not exist
                    ],
                ];
            });

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
            'vote_value' => 'required|integer|in:1,-1', // true for upvote, false for downvote
        ]);

        TopicCommentVote::updateOrCreate(
            [
                'comment_id' => $commentId,
                'user_id' => auth()->id(),
            ],
            [
                'vote_value' => $request->input('vote_value'),
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
            'comment' => $comment->comment,
            'votes' => $votes,
        ]);
    }

    // Get saved topics for the authenticated user
    public function getSavedTopics()
    {
        // Assuming UserSavedTopic is the model for cyo_user_saved_topics
        $savedTopics = UserSavedTopic::with('topic') // Load related topic if you have a relationship defined
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($savedTopics);
    }

    public function saveTopicForUser(Request $request)
    {
        $request->validate([
            'topic_id' => 'required|exists:cyo_topics,id', // Validate that the topic exists
        ]);

        $userId = auth()->id(); // Get authenticated user ID

        UserSavedTopic::create([
            'user_id' => $userId,
            'topic_id' => $request->topic_id,
        ]);

        return response()->json(['message' => 'Topic saved successfully.']);
    }

    public function destroyTopic($id)
    {
        $topic = Topic::findOrFail($id);
        $topic->delete();

        return response()->json(['message' => 'Topic deleted successfully.'], Response::HTTP_OK);
    }

    public function destroyTopicVote($id)
    {
        $vote = TopicVote::findOrFail($id);
        $vote->delete();

        return response()->json(['message' => 'Vote deleted successfully.'], Response::HTTP_OK);
    }

    public function destroySavedTopic($id)
    {
        $savedTopic = UserSavedTopic::findOrFail($id);
        $savedTopic->delete();

        return response()->json(['message' => 'Saved topic deleted successfully.'], Response::HTTP_OK);
    }

    public function destroyComment($id)
    {
        $comment = TopicComment::findOrFail($id);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully.'], Response::HTTP_OK);
    }

    public function destroyCommentVote($id)
    {
        $vote = TopicCommentVote::findOrFail($id);
        $vote->delete();

        return response()->json(['message' => 'Comment vote deleted successfully.'], Response::HTTP_OK);
    }
}
