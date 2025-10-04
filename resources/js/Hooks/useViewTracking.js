import { useRef, useEffect, useCallback, useState } from "react";
import { router } from "@inertiajs/react";

/**
 * Hook để theo dõi lượt xem bài viết bằng Intersection Observer
 * @param {number} postId - ID của bài viết
 * @param {Object} options - Tùy chọn cho Intersection Observer
 * @returns {Object} - { ref: ref để gắn vào element, isViewed: boolean }
 */
export const useViewTracking = (postId, options = {}) => {
  const elementRef = useRef(null);
  const [isViewed, setIsViewed] = useState(false);
  const [viewCount, setViewCount] = useState(0);

  const defaultOptions = {
    threshold: 0.5, // 50% của element phải visible
    rootMargin: "0px",
    triggerOnce: false, // Cho phép trigger nhiều lần
    delay: 0, // Không delay
    cooldown: 0, // Không cooldown
  };

  const observerOptions = { ...defaultOptions, ...options };

  const trackView = useCallback(async () => {
    if (!postId) return;

    try {
      // Gọi API để đăng ký lượt xem
      router.post(
        route("topics.view", postId),
        {},
        {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            setIsViewed(true);
            setViewCount((prev) => prev + 1);
          },
          onError: (errors) => {
            console.warn("Failed to track view:", errors);
          },
        }
      );
    } catch (error) {
      console.warn("Error tracking view:", error);
    }
  }, [postId]);

  useEffect(() => {
    const element = elementRef.current;
    if (!element || !postId) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];

        if (entry.isIntersecting) {
          // Đếm ngay lập tức khi visible
          trackView();
        }
      },
      {
        threshold: observerOptions.threshold,
        rootMargin: observerOptions.rootMargin,
      }
    );

    observer.observe(element);

    return () => {
      observer.disconnect();
    };
  }, [postId, trackView, observerOptions.threshold, observerOptions.rootMargin]);

  return {
    ref: elementRef,
    isViewed,
    viewCount,
  };
};

export default useViewTracking;
