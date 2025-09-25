import HomeLayout from "@/Layouts/HomeLayout";
import { Head, Link } from "@inertiajs/react";
import { useState } from "react";
import { usePage } from "@inertiajs/react";
import { CommentInput } from "@/Components/CommentInput";
import Comment from "@/Components/Comment";
import EmptyCommentsState from "@/Components/EmptyCommentsState";
import { moment } from "@/Utils/momentConfig";
import PostItem from "@/Components/PostItem";

export default function Show({ post }) {
  const { auth } = usePage().props;
  const [comments, setComments] = useState(post.comments || []);

  console.log(post);

  // Helper function to get time display
  const getTimeDisplay = (comment) => {
    if (comment.created_at) {
      const now = new Date();
      const commentTime = new Date(comment.created_at);
      const diffInMinutes = Math.floor((now - commentTime) / (1000 * 60));

      if (diffInMinutes < 1) return "Just now";
      if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
      if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`;
      return `${Math.floor(diffInMinutes / 1440)}d ago`;
    }
    return "Unknown time";
  };

  // Handle comment editing
  const handleEditComment = (commentId, newContent) => {
    const updateCommentInTree = (comments) => {
      return comments.map((comment) => {
        if (comment.id === commentId) {
          return { ...comment, content: newContent };
        }
        if (comment.replies) {
          return {
            ...comment,
            replies: updateCommentInTree(comment.replies),
          };
        }
        return comment;
      });
    };

    setComments(updateCommentInTree(comments));
    // Here you would typically make an API call to save the changes
    console.log(`Editing comment ${commentId} with content: ${newContent}`);
  };

  // Handle adding replies
  const handleReplyToComment = (parentId, content) => {
    const newReply = {
      id: Date.now().toString(), // Simple ID generation
      content: content,
      author: {
        username: auth.user.username,
        profile_name: auth.user.name || auth.user.username,
      },
      created_at: moment(new Date().toISOString()).fromNow(),
      votes: [],
      replies: [],
    };

    const addReplyToComment = (comments, level = 1) => {
      return comments.map((comment) => {
        // Found the target comment
        if (comment.id === parentId) {
          // If this is level 3, add as sibling instead of child
          if (level >= 3) {
            return comment; // Don't add here, will be handled by parent
          }

          // Normal case: add as child
          return {
            ...comment,
            replies: [...(comment.replies || []), newReply],
          };
        }

        // Search in replies
        if (comment.replies && comment.replies.length > 0) {
          // Check if target is in direct children (level 2)
          const directChild = comment.replies.find((reply) => reply.id === parentId);
          if (directChild && level === 1) {
            // Target is at level 2, add normally
            return {
              ...comment,
              replies: comment.replies.map((reply) =>
                reply.id === parentId
                  ? { ...reply, replies: [...(reply.replies || []), newReply] }
                  : reply
              ),
            };
          }

          // Check if target is in grandchildren (level 3)
          const hasLevel3Target = comment.replies.some(
            (reply) => reply.replies && reply.replies.some((r) => r.id === parentId)
          );

          if (hasLevel3Target && level === 1) {
            // Target is at level 3, add as sibling at level 3
            return {
              ...comment,
              replies: comment.replies.map((reply) => {
                if (reply.replies && reply.replies.some((r) => r.id === parentId)) {
                  return {
                    ...reply,
                    replies: [...reply.replies, newReply], // Add as sibling
                  };
                }
                return reply;
              }),
            };
          }

          // Continue searching deeper
          return {
            ...comment,
            replies: addReplyToComment(comment.replies, level + 1),
          };
        }

        return comment;
      });
    };

    const updatedComments = addReplyToComment(comments);
    console.log("Updated comments after reply:", updatedComments);
    setComments(updatedComments);
    // Here you would typically make an API call to save the reply
    console.log(`Replying to comment ${parentId} with content: ${content}`);
  };

  const handleSubmitComment = (content) => {
    const newComment = {
      id: Date.now().toString(), // Simple ID generation
      content: content,
      author: {
        username: auth.user.username,
        profile_name: auth.user.name || auth.user.username,
      },
      created_at: moment(new Date().toISOString()).fromNow(),
      votes: [],
      replies: [],
    };

    setComments([newComment, ...comments]);
    // TODO: Make an API call to save the comment
  };

  return (
    <HomeLayout activeNav="home" activeBar={null}>
      <Head title={post.title} />
      <div className="px-1 xl:min-h-screen pt-4">
        <PostItem post={post} single={true} />
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full mb-4">
          <div className="shadow !mb-4 long-shadow h-min rounded-lg bg-white post-comment-container overflow-clip">
            <div className="flex flex-col space-y-1.5 p-6 text-xl -mb-4 font-semibold max-w-sm overflow-hidden whitespace-nowrap overflow-ellipsis">
              Bình luận
            </div>
            <div className="p-6 pt-2 pb-0">
              {!auth?.user ? (
                <div className="text-base">
                  <Link
                    className="text-green-600 hover:text-green-600"
                    href={"/login?continue=" + encodeURIComponent(window.location.href)}
                  >
                    Đăng nhập
                  </Link>{" "}
                  để bình luận và tham gia thảo luận cùng cộng đồng.
                </div>
              ) : (
                <CommentInput onSubmit={handleSubmitComment} />
              )}
              <div className="pb-6 pt-2">
                {comments.length === 0 ? (
                  <EmptyCommentsState />
                ) : (
                  comments.map((comment) => (
                    <div key={comment.id} className="mt-6">
                      <Comment
                        comment={comment}
                        level={0}
                        onEdit={handleEditComment}
                        onReply={handleReplyToComment}
                        userAvatar={`https://api.chuyenbienhoa.com/v1.0/users/${auth?.user?.username}/avatar`}
                        getTimeDisplay={getTimeDisplay}
                        parentConnectorHovered={false}
                      />
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
