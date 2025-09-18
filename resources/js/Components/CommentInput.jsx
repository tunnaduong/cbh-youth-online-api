import { useEffect, useRef, useState } from "react";
import { Button } from "antd";
import { LuImage, LuType, LuArrowUp } from "react-icons/lu";
import { RiEmojiStickerLine } from "react-icons/ri";
import { usePage } from "@inertiajs/react";

export function CommentInput({ placeholder = "Nhập bình luận của bạn...", onSubmit, onCancel }) {
  const [comment, setComment] = useState("");
  const [isFocused, setIsFocused] = useState(false);
  const wrapperRef = useRef(null);
  const { auth } = usePage().props;

  const handleSubmit = () => {
    if (comment.trim()) {
      onSubmit?.(comment.trim());
      setComment("");
    }
  };

  const handleCancel = () => {
    setComment("");
    setIsFocused(false);
    onCancel?.();
  };

  const handleKeyDown = (e) => {
    if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) {
      handleSubmit();
    }
    if (e.key === "Escape") {
      handleCancel();
    }
  };

  useEffect(() => {
    function handleClickOutside(event) {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
        setIsFocused(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  return (
    <div className="w-full max-w-4xl mx-auto" ref={wrapperRef}>
      <div
        onClick={() => setIsFocused(true)}
        className={`
        relative bg-muted/30 border border-border rounded-2xl
        transition-all duration-200 dark:!border-gray-600
        ${isFocused ? "ring-2 ring-ring/20 border-ring/40" : ""}
      `}
      >
        <div className={`flex gap-3 p-4 pb-0 ${!isFocused ? "pb-3" : ""}`}>
          <img
            src={`https://api.chuyenbienhoa.com/v1.0/users/${auth?.user?.username}/avatar`}
            alt="Avatar của bạn"
            className="w-8 h-8 rounded-full flex-shrink-0"
          />
          <div className="flex-1">
            <textarea
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              onFocus={() => setIsFocused(true)}
              onKeyDown={handleKeyDown}
              placeholder={placeholder}
              rows={1}
              className="
                w-full bg-transparent border-none outline-none resize-none
                text-foreground placeholder:text-muted-foreground
                text-sm min-h-[24px] leading-6 ring-transparent focus:ring-transparent focus:border-transparent
                pl-0 pt-0 mt-1
              "
              style={{
                height: "auto",
                minHeight: "24px",
                maxHeight: "120px",
                overflowY: comment.split("\n").length > 4 ? "auto" : "hidden",
              }}
              onInput={(e) => {
                const target = e.target;
                target.style.height = "auto";
                target.style.height = Math.min(target.scrollHeight, 120) + "px";
              }}
            />
          </div>
        </div>

        <div
          className={`flex items-center justify-between px-4 pb-4 ml-11 ${
            isFocused ? "fade-in-bottom" : "hidden"
          }`}
        >
          {/* Left side controls */}
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-full hover:bg-muted">
              <RiEmojiStickerLine className="h-5 w-5 text-muted-foreground" />
            </Button>
            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-full hover:bg-muted">
              <LuImage className="h-4 w-4 text-muted-foreground" />
            </Button>
            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-full hover:bg-muted">
              <LuType className="h-4 w-4 text-muted-foreground" />
            </Button>
          </div>

          {/* Right side actions */}
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              onClick={handleSubmit}
              disabled={!comment.trim()}
              className="!bg-primary-500 hover:!bg-primary-600 disabled:!bg-primary-200 text-white rounded-full h-8 w-8 p-0"
            >
              <LuArrowUp className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
