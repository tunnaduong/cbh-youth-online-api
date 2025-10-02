import HomeLayout from "@/Layouts/HomeLayout";
import { Head, Link } from "@inertiajs/react";
import { useState, useEffect } from "react";
import { usePage, router } from "@inertiajs/react";
import { CommentInput } from "@/Components/CommentInput";
import Comment from "@/Components/Comment";
import EmptyCommentsState from "@/Components/EmptyCommentsState";
import PostItem from "@/Components/PostItem";
import { message } from "antd";

export default function Show({ post, ogImage, comments: initialComments }) {
  const { auth } = usePage().props;
  const [comments, setComments] = useState(initialComments || []);
  const [currentPost, setCurrentPost] = useState(post);

  // Sync local state when server props update (e.g., after reload)
  useEffect(() => {
    setComments(initialComments || []);
  }, [initialComments]);

  // Helper function to get time display
  const getTimeDisplay = (comment) => {
    if (comment.created_at) {
      const now = new Date();
      const commentTime = new Date(comment.created_at);
      const diffInMinutes = Math.floor((now - commentTime) / (1000 * 60));

      if (diffInMinutes < 1) return "Vừa xong";
      if (diffInMinutes < 60) return `${diffInMinutes} phút trước`;
      if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)} giờ trước`;
      return `${Math.floor(diffInMinutes / 1440)} ngày trước`;
    }
    return "Không rõ thời gian";
  };

  // Handle comment editing
  const handleEditComment = (commentId, newContent) => {
    // Store original content for rollback
    const originalComments = [...comments];

    // Optimistically update comment in UI
    const updateCommentInTree = (comments) => {
      return comments.map((comment) => {
        if (comment.id === commentId) {
          return {
            ...comment,
            content: newContent,
          };
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
    message.success("Bình luận đã được cập nhật thành công");

    router.put(
      route("comments.update", commentId),
      {
        comment: newContent,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          // Comment was successfully updated, no need to do anything
        },
        onError: (errors) => {
          // Rollback to original state on error
          setComments(originalComments);
          if (errors.comment) {
            message.error(errors.comment);
          } else {
            message.error("Có lỗi xảy ra khi sửa bình luận");
          }
        },
      }
    );
  };

  // Handle adding replies
  const handleReplyToComment = (parentId, content) => {
    // Optimistically add reply to UI
    const newReply = {
      id: Date.now().toString(),
      content: content,
      author: {
        username: auth.user.username,
        profile_name: auth.profile.profile_name || auth.user.username,
      },
      created_at: "vài giây trước",
      votes: [],
      replies: [],
      isPending: true,
    };

    let level2ParentId = null;

    const addReplyToComment = (comments, level = 1, parentLevel2Id = null) => {
      return comments.map((comment) => {
        // Found the target comment
        if (comment.id === parentId) {
          if (level >= 3) {
            // Store level 2 parent ID when target is at level 3 or deeper
            level2ParentId = parentLevel2Id;
            return comment;
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
                  level2ParentId = reply.id; // Store level 2 parent ID
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
            replies: addReplyToComment(
              comment.replies,
              level + 1,
              level === 1 ? comment.id : parentLevel2Id
            ),
          };
        }

        return comment;
      });
    };

    const updatedComments = addReplyToComment(comments);
    setComments(updatedComments);
    message.success("Đã trả lời bình luận thành công");

    router.post(
      route("comments.store"),
      {
        comment: content,
        replying_to: level2ParentId || parentId, // Use level 2 parent ID if available
        topic_id: post.id,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          // Refresh only comments from server to replace pending items
          router.reload({ only: ["comments"], preserveScroll: true, preserveState: true });
        },
        onError: (errors) => {
          // Remove the optimistic reply on error
          setComments(comments);
          if (errors.comment) {
            message.error(errors.comment);
          } else {
            message.error("Có lỗi xảy ra khi trả lời bình luận");
          }
        },
      }
    );
  };

  const handleSubmitComment = (content) => {
    // Optimistically add comment to UI
    const tempComment = {
      id: Date.now().toString(),
      content: content,
      author: {
        username: auth.user.username,
        profile_name: auth.profile.profile_name || auth.user.username,
      },
      created_at: "vài giây trước",
      votes: [],
      replies: [],
      isPending: true,
    };

    setComments([tempComment, ...comments]);
    message.success("Bình luận đã được đăng thành công");

    router.post(
      route("comments.store"),
      {
        comment: content,
        topic_id: post.id,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          // Refresh only comments from server to replace pending items
          router.reload({ only: ["comments"], preserveScroll: true, preserveState: true });
        },
        onError: (errors) => {
          // Remove the optimistic comment on error
          setComments(comments.filter((c) => c.id !== tempComment.id));
          if (errors.comment) {
            message.error(errors.comment);
          } else {
            message.error("Có lỗi xảy ra khi đăng bình luận");
          }
        },
      }
    );
  };

  const handleDeleteComment = (commentId) => {
    // Store original comments for rollback
    const originalComments = [...comments];

    // Optimistically remove comment from UI
    const removeCommentFromTree = (comments) => {
      return comments
        .filter((comment) => comment.id !== commentId)
        .map((comment) => {
          if (comment.replies) {
            return {
              ...comment,
              replies: removeCommentFromTree(comment.replies),
            };
          }
          return comment;
        });
    };

    setComments(removeCommentFromTree(comments));
    message.success("Bình luận đã được xóa thành công");

    router.delete(
      route("comments.destroy", commentId),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          // Comment was successfully deleted, no need to do anything
        },
        onError: (errors) => {
          // Rollback to original state on error
          setComments(originalComments);
          if (errors.comment) {
            message.error(errors.comment);
          } else {
            message.error("Có lỗi xảy ra khi xóa bình luận");
          }
        },
      }
    );
  };

  const handleVote = (postId, value) => {
    if (!auth?.user) {
      router.visit(route("login") + "?continue=" + encodeURIComponent(window.location.href));
      message.error("Bạn cần đăng nhập để thực hiện hành động này");
      return;
    }

    setCurrentPost((prev) => {
      // Kiểm tra user đã vote chưa
      let existingVote = prev.votes.find((v) => v.username === auth.user.username);
      let newVotes;

      if (existingVote) {
        if (existingVote.vote_value === value) {
          // Ấn lại -> bỏ vote
          newVotes = prev.votes.filter((v) => v.username !== auth.user.username);
        } else {
          // Đổi hướng vote
          newVotes = prev.votes.map((v) =>
            v.username === auth.user.username ? { ...v, vote_value: value } : v
          );
        }
      } else {
        // Thêm vote mới
        newVotes = [...prev.votes, { username: auth.user.username, vote_value: value }];
      }

      return { ...prev, votes: newVotes };
    });

    // Gọi backend để sync
    router.post(
      route("topics.vote", postId),
      { vote_value: value },
      { showProgress: false, preserveScroll: true }
    );
  };

  return (
    <HomeLayout activeNav="home" activeBar={null}>
      <Head title={post.title}>
        <meta property="og:image" content={ogImage} />
        <meta name="twitter:image" content={ogImage} />
      </Head>
      <div className="px-1 xl:min-h-screen pt-4">
        <PostItem post={currentPost} single={true} onVote={handleVote} />
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full mb-4">
          <div className="shadow !mb-4 long-shadow h-min rounded-lg bg-white post-comment-container overflow-clip">
            <div className="flex flex-col space-y-1.5 p-6 text-xl -mb-4 font-semibold max-w-sm overflow-hidden whitespace-nowrap overflow-ellipsis">
              Bình luận
            </div>
            <div className="p-6 pt-2 pb-0 relative">
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
                {!Array.isArray(comments) || comments.length === 0 ? (
                  <EmptyCommentsState />
                ) : (
                  comments.map((comment) => (
                    <div key={comment.id} className="mt-6">
                      <Comment
                        comment={comment}
                        level={0}
                        onEdit={handleEditComment}
                        onReply={handleReplyToComment}
                        onDelete={handleDeleteComment}
                        userAvatar={`https://api.chuyenbienhoa.com/v1.0/users/${auth?.user?.username}/avatar`}
                        getTimeDisplay={getTimeDisplay}
                        parentConnectorHovered={false}
                      />
                    </div>
                  ))
                )}
              </div>
              <div className="absolute bottom-0 left-0 w-full h-6 bg-white"></div>
            </div>
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
