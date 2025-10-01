import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import PostItem from "@/Components/PostItem";
import { useState, useEffect, useCallback, useRef } from "react";
import axios from "axios";
import Lottie from "lottie-react";
import refresh from "@/assets/refresh.json";

export default function Index({ youthNews: initialYouthNews, pagination: initialPagination }) {
  const [youthNews, setYouthNews] = useState(initialYouthNews || []);
  const [pagination, setPagination] = useState(initialPagination || {});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const sentinelRef = useRef(null);

  // Load more youth news function
  const loadMoreYouthNews = useCallback(async () => {
    if (loading || !pagination.has_more_pages) return;

    setLoading(true);
    setError(null);

    try {
      const response = await axios.get("/api/youth-news", {
        params: {
          page: pagination.current_page + 1,
        },
      });

      const { youthNews: newYouthNews, pagination: newPagination } = response.data;

      setYouthNews((prevYouthNews) => [...prevYouthNews, ...newYouthNews]);
      setPagination(newPagination);
    } catch (err) {
      setError("Không thể tải thêm tin tức. Vui lòng thử lại.");
      console.error("Error loading more youth news:", err);
    } finally {
      setLoading(false);
    }
  }, [loading, pagination.has_more_pages, pagination.current_page]);

  // Intersection Observer for precise infinite scroll detection
  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel) return;

    // Add debounce to prevent rapid successive loads
    let debounceTimer = null;

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        if (entry.isIntersecting && !loading && pagination.has_more_pages) {
          // Clear existing timer
          if (debounceTimer) {
            clearTimeout(debounceTimer);
          }

          // Set a small delay to debounce rapid intersections
          debounceTimer = setTimeout(() => {
            // Double-check conditions before loading
            if (!loading && pagination.has_more_pages) {
              loadMoreYouthNews();
            }
          }, 100);
        }
      },
      {
        root: null,
        rootMargin: "50px", // Start loading 50px before the sentinel is visible
        threshold: 0.1,
      }
    );

    observer.observe(sentinel);

    return () => {
      if (sentinel) {
        observer.unobserve(sentinel);
      }
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }
    };
  }, [loadMoreYouthNews, loading, pagination.has_more_pages]);

  return (
    <HomeLayout activeNav="home" activeBar="news">
      <Head title="Tin tức Đoàn" />

      <div className="px-1 xl:min-h-screen pt-4 md:max-w-[775px] mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6 px-1">
          Tin tức Đoàn
        </h1>

        <div className="space-y-6">
          {youthNews.map((post) => (
            <PostItem key={post.id} post={post} />
          ))}
        </div>

        {/* Invisible sentinel for intersection observer */}
        {pagination.has_more_pages && <div ref={sentinelRef} className="h-1 w-full" />}

        {/* Loading indicator - subtle like Facebook */}
        {loading && (
          <div className="flex justify-center items-center py-4">
            <Lottie animationData={refresh} loop={true} style={{ width: 40, height: 40 }} />
            <span className="ml-2 text-gray-500 dark:text-gray-400 text-sm">Đang tải...</span>
          </div>
        )}

        {/* Error message */}
        {error && (
          <div className="flex justify-center items-center py-8">
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
              {error}
              <button onClick={loadMoreYouthNews} className="ml-2 underline hover:no-underline">
                Thử lại
              </button>
            </div>
          </div>
        )}

        {/* End of posts message */}
        {!pagination.has_more_pages && youthNews.length > 0 && (
          <div className="text-center py-8 text-gray-500 dark:text-gray-400">
            <img src="/images/pingpong.png" className="h-20 mx-auto" />
            <br />
            Bạn đã xem hết tất cả tin tức
          </div>
        )}

        {/* No posts message */}
        {youthNews.length === 0 && !loading && (
          <div className="text-center py-8 text-gray-500 dark:text-gray-400">
            <img src="/images/pingpong.png" className="h-20 mx-auto" />
            <br />
            Chưa có tin tức nào
          </div>
        )}
      </div>
    </HomeLayout>
  );
}
