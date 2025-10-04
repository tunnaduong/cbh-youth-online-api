import React, { useState, useMemo } from "react";
import { Link } from "@inertiajs/react";
import HelpCenterLayout from "@/Layouts/HelpCenterLayout";
import LeftSidebar from "@/Components/HelpCenter/LeftSidebar";
import { helpArticles } from "@/Data/helpArticles";

export default function Index({ auth }) {
  const [searchQuery, setSearchQuery] = useState("");

  // Tìm kiếm trong tất cả bài viết
  const searchResults = useMemo(() => {
    if (!searchQuery.trim()) return [];

    const query = searchQuery.toLowerCase();
    const results = [];

    helpArticles.forEach((category) => {
      category.articles.forEach((article) => {
        const titleMatch = article.title.toLowerCase().includes(query);
        const contentMatch = article.content.toLowerCase().includes(query);

        if (titleMatch || contentMatch) {
          results.push({
            ...article,
            category: category.category,
            categorySlug: category.slug,
          });
        }
      });
    });

    return results;
  }, [searchQuery]);

  return (
    <HelpCenterLayout auth={auth} title="Trung tâm trợ giúp">
      <LeftSidebar />
      <main className="w-full md:w-3/4 px-4">
        <div className="p-6 bg-white dark:bg-[#3c3c3c] shadow-sm sm:rounded-xl">
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
            Chào mừng đến với Trung tâm trợ giúp!
          </h1>
          <p className="mt-2 text-gray-600 dark:text-gray-300">
            Chúng tôi có thể giúp gì cho bạn? Hãy chọn một chủ đề bên dưới hoặc sử dụng thanh tìm
            kiếm để tìm câu trả lời bạn cần.
          </p>

          {/* Thanh tìm kiếm */}
          <div className="mt-6">
            <div className="relative">
              <input
                type="text"
                placeholder="Tìm kiếm bài viết trợ giúp..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full px-4 py-3 pl-10 pr-4 text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              />
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg
                  className="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  />
                </svg>
              </div>
            </div>
          </div>

          {/* Hiển thị kết quả tìm kiếm */}
          {searchQuery && (
            <div className="mt-6">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Kết quả tìm kiếm cho "{searchQuery}" ({searchResults.length} kết quả)
              </h2>
              {searchResults.length > 0 ? (
                <div className="space-y-4">
                  {searchResults.map((article, index) => (
                    <div
                      key={`${article.categorySlug}-${article.slug}-${index}`}
                      className="p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:shadow-md transition-shadow"
                    >
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <Link
                            href={`/help/${article.categorySlug}/${article.slug}`}
                            className="text-lg font-medium text-primary-500 hover:underline"
                          >
                            {article.title}
                          </Link>
                          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {article.category}
                          </p>
                          <div
                            className="mt-2 text-sm text-gray-600 dark:text-gray-300 line-clamp-2"
                            dangerouslySetInnerHTML={{
                              __html:
                                article.content.replace(/<[^>]*>/g, "").substring(0, 150) + "...",
                            }}
                          />
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <p className="text-gray-500 dark:text-gray-400">
                    Không tìm thấy bài viết nào phù hợp với từ khóa "{searchQuery}"
                  </p>
                </div>
              )}
            </div>
          )}

          {/* Hiển thị danh mục khi không có tìm kiếm */}
          {!searchQuery && (
            <div className="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {helpArticles.map((category) => (
                <div
                  key={category.slug}
                  className="p-4 border border-gray-200 dark:border-gray-600 rounded-lg"
                >
                  <h3 className="font-semibold text-lg text-gray-900 dark:text-gray-100">
                    {category.category}
                  </h3>
                  <ul className="mt-2 space-y-2">
                    {category.articles.slice(0, 3).map((article) => (
                      <li key={article.slug}>
                        <Link
                          href={`/help/${category.slug}/${article.slug}`}
                          className="text-sm text-primary-500 hover:underline"
                        >
                          {article.title}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          )}
        </div>
      </main>
    </HelpCenterLayout>
  );
}
