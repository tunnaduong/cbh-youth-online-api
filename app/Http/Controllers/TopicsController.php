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
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;

class TopicsController extends Controller
{
    /**
     * Convert markdown to HTML using CommonMark
     */
    private function convertMarkdownToHtml($markdown)
    {
        $config = [
            'renderer' => [
                'soft_break' => "<br>\n",
            ],
        ];

        $converter = new CommonMarkConverter($config);
        $converter->getEnvironment()->addExtension(new AutolinkExtension());

        return $converter->convert($markdown)->getContent();
    }
    // GET /topics – Get list of topics
    public function index(Request $request)
    {
        // Fetch topics from the database with pagination
        $query = Topic::withCount(['views', 'comments'])
            ->where('hidden', 0)
            ->orderBy('created_at', 'desc')
            ->with(['user', 'votes.user', 'cdnUserContent']);

        // Filter by privacy based on authentication and following status
        if (auth()->check()) {
            $userId = auth()->id();

            // Get list of user IDs that the current user is following
            $followingIds = \App\Models\Follower::where('follower_id', $userId)
                ->pluck('followed_id')
                ->toArray();

            $query->where(function ($q) use ($userId, $followingIds) {
                $q->where(function ($subQ) {
                    // Public posts (privacy = public AND hidden = 0)
                    $subQ->where('privacy', 'public')
                        ->where('hidden', 0);
                })
                    ->orWhere('user_id', $userId) // User's own posts (including private ones)
                    ->orWhere(function ($subQ) use ($followingIds) {
                        // Followers posts (privacy = followers AND hidden = 0)
                        $subQ->where('privacy', 'followers')
                            ->where('hidden', 0)
                            ->whereIn('user_id', $followingIds);
                    });
            });
        } else {
            // For non-authenticated users, only show public posts
            $query->where('privacy', 'public')
                ->where('hidden', 0);
        }

        $topics = $query->paginate(10) // Paginate with 10 items per page
            ->through(function ($topic) use ($request) {
                // Check if user is moderator/admin (you may need to adjust this logic based on your role system)
                $isModerator = $request->user() && (
                    $request->user()->hasRole('admin') ||
                    $request->user()->hasRole('moderator') ||
                    $request->user()->id === 1 // Assuming user ID 1 is admin, adjust as needed
                );

                $topicData = [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'content' => $topic->description,
                    'image_urls' => $topic->getImageUrls()->map(function ($content) {
                        return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                    })->all(),
                    'author' => $topic->anonymous && !$isModerator ? [
                        'id' => null,
                        'username' => 'Ẩn danh',
                        'email' => null,
                        'profile_name' => 'Người dùng ẩn danh',
                        'verified' => false,
                    ] : [
                        'id' => $topic->user->id,
                        'username' => $topic->user->username,
                        'email' => $topic->user->email,
                        'profile_name' => $topic->user->profile->profile_name ?? null,
                        'verified' => $topic->user->profile->verified == 1 ?? false ? true : false,
                    ],
                    'anonymous' => $topic->anonymous,
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

        // Return the paginated topics as a JSON response
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

    public function show(Request $request, $id)
    {
        // Find the topic with related data or return a 404 error if not found
        $topic = Topic::with(['user.profile', 'votes.user', 'comments.user.profile', 'views', 'cdnUserContent'])
            ->find($id);

        if (!$topic) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404); // Not Found
        }

        // Load comments with their respective votes and voter usernames
        $comments = $topic->comments()
            ->whereNull('replying_to')
            ->with([
                'user.profile',
                'votes.user',
                'replies' => function ($q) {
                    $q->with([
                        'user.profile',
                        'votes.user',
                    ]); // Load 5 replies per request
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get();


        $formattedComments = $comments->map(function ($comment) {
            return [
                'id' => $comment->id,
                'content' => $comment->comment,
                'author' => [
                    'id' => $comment->user->id,
                    'username' => $comment->user->username,
                    'email' => $comment->user->email,
                    'profile_name' => $comment->user->profile->profile_name ?? null,
                    'verified' => $comment->user->profile->verified == 1 ?? false ? true : false,
                ],
                'created_at' => $comment->created_at->diffForHumans(),
                'votes' => $comment->votes->map(fn($vote) => [
                    'user_id' => $vote->user_id,
                    'username' => $vote->user->username,
                    'vote_value' => $vote->vote_value,
                ]),
                'replies' => $comment->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'content' => $reply->comment,
                        'author' => [
                            'id' => $reply->user->id,
                            'username' => $reply->user->username,
                            'email' => $reply->user->email,
                            'profile_name' => $reply->user->profile->profile_name ?? null,
                            'verified' => $reply->user->profile->verified == 1 ?? false ? true : false,
                        ],
                        'created_at' => $reply->created_at->diffForHumans(),
                        'votes' => $reply->votes->map(fn($vote) => [
                            'user_id' => $vote->user_id,
                            'username' => $vote->user->username,
                            'vote_value' => $vote->vote_value,
                        ]),
                        'replies' => $reply->replies->map(function ($subReply) {
                            return [
                                'id' => $subReply->id,
                                'content' => $subReply->comment,
                                'author' => [
                                    'id' => $subReply->user->id,
                                    'username' => $subReply->user->username,
                                    'email' => $subReply->user->email,
                                    'profile_name' => $subReply->user->profile->profile_name ?? null,
                                    'verified' => $subReply->user->profile->verified == 1 ?? false ? true : false,
                                ],
                                'created_at' => $subReply->created_at->diffForHumans(),
                                'votes' => $subReply->votes->map(fn($vote) => [
                                    'user_id' => $vote->user_id,
                                    'username' => $vote->user->username,
                                    'vote_value' => $vote->vote_value,
                                ]),
                            ];
                        }),
                    ];
                }),
            ];
        });



        // Check if user is moderator/admin
        $isModerator = $request->user() && (
            $request->user()->hasRole('admin') ||
            $request->user()->hasRole('moderator') ||
            $request->user()->id === 1 // Assuming user ID 1 is admin, adjust as needed
        );

        // Map the topic details into the response format
        $topicData = [
            'id' => $topic->id,
            'title' => $topic->title,
            'content' => $topic->description,
            'image_urls' => $topic->getImageUrls()->map(function ($content) {
                return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
            })->all(),
            'author' => $topic->anonymous && !$isModerator ? [
                'id' => null,
                'username' => 'Ẩn danh',
                'email' => null,
                'profile_name' => 'Người dùng ẩn danh',
                'verified' => false,
            ] : [
                'id' => $topic->user->id,
                'username' => $topic->user->username,
                'email' => $topic->user->email,
                'profile_name' => $topic->user->profile->profile_name ?? null,
                'verified' => $topic->user->profile->verified == 1 ?? false ? true : false,
            ],
            'anonymous' => $topic->anonymous,
            'time' => Carbon::parse($topic->created_at)->diffForHumans(),
            'comments' => $this->roundToNearestFive($topic->comments->count()) . '+',
            'views' => $topic->views->count(),
            'votes' => $topic->votes->map(function ($vote) {
                return [
                    'username' => $vote->user->username,
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

        return response()->json(['topic' => $topicData, 'comments' => $formattedComments]);
    }

    // POST /topics – Create a new topic
    public function store(Request $request)
    {
        // Debug: Log the incoming request data
        \Log::info('Topic creation request data:', ['data' => $request->all()]);
        \Log::info('Request method:', ['method' => $request->method()]);
        \Log::info('Request headers:', ['headers' => $request->headers->all()]);

        // Validate the request data
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'subforum_id' => 'nullable|exists:cyo_forum_subforums,id', // Kiểm tra subforum_id
            'image_files' => 'nullable|array',
            'image_files.*' => 'file|image|max:10240', // 10MB max for each image
            'visibility' => 'nullable|integer|in:0,1', // 0: public, 1: private (for hidden field)
            'privacy' => 'nullable|string|in:public,followers', // public, followers
            'anonymous' => 'nullable|boolean', // Anonymous posting
        ]);

        $cdnImageIds = [];

        // Handle multiple image uploads if present
        if ($request->hasFile('image_files')) {
            $files = $request->file('image_files');

            foreach ($files as $file) {
                $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images', $fileName, 'public');

                // Create UserContent record for each image
                $userContent = UserContent::create([
                    'user_id' => auth()->id(),
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                $cdnImageIds[] = $userContent->id;
            }
        }

        // Convert array of IDs to comma-separated string for database storage
        $cdnImageId = !empty($cdnImageIds) ? implode(',', $cdnImageIds) : null;

        // Convert markdown description to HTML
        $contentHtml = $this->convertMarkdownToHtml($request->description);

        $topic = Topic::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description, // Keep original markdown
            'content_html' => $contentHtml, // Store converted HTML
            'subforum_id' => $request->subforum_id, // Gán giá trị cho subforum_id
            'cdn_image_id' => $cdnImageId,
            'hidden' => $request->visibility,
            'privacy' => $request->privacy ?? 'public', // Default to public if not provided
            'anonymous' => $request->boolean('anonymous', false), // Default to false if not provided
        ]);

        // Debug: Log the created topic
        \Log::info('Topic created successfully:', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'description' => $topic->description,
                'content_html' => $topic->content_html,
                'user_id' => $topic->user_id,
                'subforum_id' => $topic->subforum_id,
                'cdn_image_id' => $topic->cdn_image_id,
            ]
        ]);

        // Load the user profile to get profile_name
        $author = $topic->user()->with('profile')->first();

        // Check if this is an API request or web request
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'id' => $topic->id,
                'title' => $topic->title,
                'content' => $topic->description,
                'image_urls' => $topic->getImageUrls()->map(function ($content) {
                    return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                })->all(),
                'author' => $topic->anonymous ? [
                    'id' => null,
                    'username' => 'Ẩn danh',
                    'email' => null,
                    'profile_name' => 'Người dùng ẩn danh',
                    'verified' => false,
                ] : [
                    'id' => $author->id,
                    'username' => $author->username,
                    'email' => $author->email,
                    'profile_name' => $author->profile->profile_name ?? null, // Ensure profile_name is included
                ],
                'anonymous' => $topic->anonymous,
                'time' => Carbon::parse($topic->created_at)->diffForHumans(), // You can dynamically calculate the time difference if needed
                'comments' => '00+', // Adjust this based on actual comment count if necessary
                'views' => 0, // Initialize view count as 0 or load actual views
                'votes' => [], // Initialize empty votes array or load actual votes
                'saved' => false, // Default to false or check if the user has saved the topic
            ], 201);
        }

        // For web requests (Inertia), return a redirect or success response
        return back()->with('success', 'Bài viết đã được tạo thành công!');
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

    public function getReplies(Request $request, $commentId)
    {
        $comment = TopicComment::findOrFail($commentId);

        $replies = $comment->replies()
            ->paginate(5); // Load replies in chunks

        return response()->json($replies);
    }


    // Add a comment to a topic
    public function addComment(Request $request, $topicId)
    {
        $request->validate([
            'comment' => 'required|string',
            'replying_to' => 'nullable|exists:cyo_topic_comments,id', // Validate the replying_to field
        ]);

        $comment = TopicComment::create([
            'replying_to' => $request->replying_to,
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
            'vote_value' => 'required|integer|in:1,-1,0', // true for upvote, false for downvote
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
        $userId = Auth::id();

        $result = UserSavedTopic::where('user_id', $userId)
            ->with(['topic.user.profile'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($result->isEmpty()) {
            return response()->json([]);
        }

        $mappedTopics = $result->map(function ($savedTopic) {
            return [
                'id' => $savedTopic->id,
                'user_id' => $savedTopic->user_id,
                'topic_id' => $savedTopic->topic_id,
                'created_at' => $savedTopic->created_at->diffForHumans(),
                'updated_at' => $savedTopic->updated_at,
                'topic' => [
                    'id' => $savedTopic->topic->id,
                    'subforum_id' => $savedTopic->topic->subforum_id,
                    'user_id' => $savedTopic->topic->user->id,
                    'title' => $savedTopic->topic->title,
                    'content' => $savedTopic->topic->description,
                    'created_at' => $savedTopic->topic->created_at,
                    'updated_at' => $savedTopic->topic->updated_at,
                    'pinned' => $savedTopic->topic->pinned,
                    'image_urls' => $savedTopic->topic->getImageUrls()->map(function ($content) {
                        return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                    })->all(),
                    'author' => [
                        'id' => $savedTopic->topic->user->id,
                        'username' => $savedTopic->topic->user->username,
                        'email' => $savedTopic->topic->user->email,
                        'profile_name' => $savedTopic->topic->user->profile->profile_name ?? null,
                    ],
                ]
            ];
        });

        return response()->json($mappedTopics);
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
