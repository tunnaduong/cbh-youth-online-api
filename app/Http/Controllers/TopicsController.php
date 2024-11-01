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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TopicsController extends Controller
{
    // GET /topics – Get list of topics
    public function index(Request $request)
    {
        // Fetch topics from the database
        $topics = Topic::withCount(['views', 'comments'])
            ->orderBy('created_at', 'desc')
            ->with(['user', 'votes.user', 'cdnUserContent'])
            ->get()
            ->map(function ($topic) use ($request) {
                $topicData = [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'content' => nl2br(e($topic->description)),
                    'image_url' => $topic->cdnUserContent ? Storage::url($topic->cdnUserContent->file_path) : null,
                    'author' => [
                        'id' => $topic->user->id,
                        'username' => $topic->user->username,
                        'email' => $topic->user->email,
                        'profile_name' => $topic->user->profile->profile_name ?? null,
                    ],
                    'time' => Carbon::parse($topic->created_at)->diffForHumans(),  // Time in human-readable format
                    'comments' => $this->roundToNearestFive($topic->comments_count) . '+', // Comment count in '05+' format
                    'views' => $topic->views_count, // Fallback to 0 if 'views' is null
                    'votes' => $topic->votes->map(function ($vote) {
                        return [
                            'username' => $vote->user->username, // Assuming votes relation includes the user
                            'vote_value' => $vote->vote_value,
                            'created_at' => $vote->created_at->toISOString(),
                            'updated_at' => $vote->updated_at->toISOString(),
                        ];
                    }),
                ];

                // Check if the user is authenticated
                if ($request->user()) {
                    $topicData['saved'] = $topic->isSavedByUser($request->user()->id);
                } else {
                    $topicData['saved'] = false;
                }

                return $topicData;
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

    public function show($id)
    {
        // Find the topic with related data or return a 404 error if not found
        $topic = Topic::with(['user.profile', 'votes.user', 'comments.user.profile', 'views', 'cdnUserContent'])
            ->find($id);

        if (!$topic) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404); // Not Found
        }

        // Load comments with their respective votes and voter usernames
        $comments = $topic->comments()->orderBy('created_at', 'desc')->with(['user.profile', 'votes.user'])
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->comment,
                    'author' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'profile_name' => $comment->user->profile->profile_name ?? null,
                    ],
                    'created_at' => $comment->created_at->diffForHumans(),
                    'votes' => $comment->votes->map(function ($vote) {
                        return [
                            'user_id' => $vote->user_id,
                            'username' => $vote->user->username, // Include the voter's username
                            'vote_value' => $vote->vote_value,   // Assuming 1 for upvote, -1 for downvote
                        ];
                    }),
                ];
            });

        // Map the topic details into the response format
        $topicData = [
            'id' => $topic->id,
            'title' => $topic->title,
            'content' => nl2br(e($topic->description)),
            'image_url' => $topic->cdnUserContent ? Storage::url($topic->cdnUserContent->file_path) : null, // Assuming the relationship is named 'cdnImage'
            'author' => [
                'id' => $topic->user->id,
                'username' => $topic->user->username,
                'email' => $topic->user->email,
                'profile_name' => $topic->user->profile->profile_name ?? null,
            ],
            'time' => Carbon::parse($topic->created_at)->diffForHumans(),
            'comments_count' => $this->roundToNearestFive($topic->comments->count()) . '+',
            'views_count' => $topic->views->count(),
            'votes' => $topic->votes->map(function ($vote) {
                return [
                    'username' => $vote->user->username,
                    'vote_value' => $vote->vote_value,
                    'created_at' => $vote->created_at->toISOString(),
                    'updated_at' => $vote->updated_at->toISOString(),
                ];
            }),
            'comments' => $comments,
        ];

        return response()->json($topicData);
    }

    // POST /topics – Create a new topic
    public function store(Request $request)
    {
        // Validate the request data
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'subforum_id' => 'nullable|exists:cyo_forum_subforums,id', // Kiểm tra subforum_id
            'cdn_image_id' => 'nullable|exists:cyo_cdn_user_content,id',
        ]);

        $topic = Topic::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'subforum_id' => $request->subforum_id, // Gán giá trị cho subforum_id
            'cdn_image_id' => $request->cdn_image_id,
        ]);

        // Load the user profile to get profile_name
        $author = $topic->user()->with('profile')->first();



        return response()->json([
            'id' => $topic->id,
            'title' => $topic->title,
            'content' => nl2br(e($topic->description)),
            'image_url' => $topic->cdnUserContent ? Storage::url($topic->cdnUserContent->file_path) : null,
            'author' => [
                'id' => $author->id,
                'username' => $author->username,
                'email' => $author->email,
                'profile_name' => $author->profile->profile_name ?? null, // Ensure profile_name is included
            ],
            'time' => Carbon::parse($topic->created_at)->diffForHumans(), // You can dynamically calculate the time difference if needed
            'comments' => '00+', // Adjust this based on actual comment count if necessary
            'views' => 0, // Initialize view count as 0 or load actual views
            'votes' => [], // Initialize empty votes array or load actual votes
            'saved' => false, // Default to false or check if the user has saved the topic
        ], 201);
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
            'vote_value' => 'required|integer|in:1,-1,0', // true for upvote, false for downvote
        ]);

        // Retrieve the authenticated user
        $user = Auth::user();

        $vote = TopicVote::updateOrCreate(
            [
                'topic_id' => $topicId,
                'user_id' => auth()->id(),
            ],
            [
                'vote_value' => $request->input('vote_value'),
            ]
        );

        // Return a custom response
        return response()->json([
            'message' => 'Vote registered',
            'vote' => [
                'vote_value' => $vote->vote_value,
                'username' => $user->username
            ]
        ]);
    }

    // Get comments for a topic
    public function getComments($topicId)
    {
        $comments = TopicComment::with(['user', 'user.profile'])
            ->where('topic_id', $topicId)->orderBy('created_at', 'desc')
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

        // Load the comment's author profile details
        $author = $comment->user()->with('profile')->first();

        return response()->json([
            'id' => $comment->id,
            'content' => $comment->comment,
            'author' => [
                'id' => $author->id,
                'username' => $author->username,
                'profile_name' => $author->profile->profile_name ?? null,
            ],
            'created_at' => Carbon::parse($comment->created_at)->diffForHumans(),
            'votes' => [], // Initialize an empty array for votes
        ], 201); // Created status
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
        $savedTopics = UserSavedTopic::where('user_id', Auth::id()) // Adjust to get the correct user
            ->with(['topic.user.profile']) // Eager load the topic's user and profile
            ->get()
            ->map(function ($savedTopic) {
                return [
                    'id' => $savedTopic->id,
                    'user_id' => $savedTopic->user_id,
                    'topic_id' => $savedTopic->topic_id,
                    'created_at' => $savedTopic->created_at,
                    'updated_at' => $savedTopic->updated_at,
                    'topic' => [
                        'id' => $savedTopic->topic->id,
                        'subforum_id' => $savedTopic->topic->subforum_id,
                        'user_id' => $savedTopic->topic->user->id,
                        'title' => $savedTopic->topic->title,
                        'description' => $savedTopic->topic->description,
                        'created_at' => $savedTopic->topic->created_at,
                        'updated_at' => $savedTopic->topic->updated_at,
                        'pinned' => $savedTopic->topic->pinned,
                        'image_url' => $savedTopic->topic->image_url,
                        'author' => [
                            'id' => $savedTopic->topic->user->id,
                            'username' => $savedTopic->topic->user->username,
                            'email' => $savedTopic->topic->user->email,
                            'profile_name' => $savedTopic->topic->user->profile->profile_name ?? null, // Using profile relation
                        ],
                    ]
                ];
            });

        return response()->json($savedTopics);
    }

    public function saveTopicForUser(Request $request)
    {
        $request->validate([
            'topic_id' => 'required|exists:cyo_topics,id', // Validate that the topic exists
        ]);

        $userId = auth()->id(); // Get authenticated user ID

        // Check if the user has already saved the topic
        $exists = DB::table('cyo_user_saved_topics')
            ->where('topic_id', $request->topic_id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            // If the record exists, return a message or take appropriate action
            return response()->json(['message' => 'This topic is already saved by the user.'], 409); // 409 Conflict
        }


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

    public function destroySavedTopic($topicId, Request $request)
    {
        // Optionally, validate the user is authenticated
        $userId = $request->user()->id; // Get the authenticated user's ID

        // Find the saved topic by topic_id and user_id
        $savedTopic = UserSavedTopic::where('topic_id', $topicId)
            ->where('user_id', $userId)
            ->first();

        if (!$savedTopic) {
            return response()->json(['message' => 'Saved topic not found'], 404);
        }

        // Delete the saved topic
        $savedTopic->delete();

        return response()->json(['message' => 'Saved topic deleted successfully'], 200);
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
