import React from "react";
import { Link, Head } from "@inertiajs/react";
import HelpCenterLayout from "@/Layouts/HelpCenterLayout";
import LeftSidebar from "@/Components/HelpCenter/LeftSidebar";
import TopicList from "@/Components/HelpCenter/TopicList";
import { helpArticles } from "@/Data/helpArticles";
import { HiChevronRight } from "react-icons/hi";

export default function Show({ auth, categorySlug, articleSlug }) {
  const category = helpArticles.find((cat) => cat.slug === categorySlug);
  const article = category?.articles.find((art) => art.slug === articleSlug);

  if (!article) {
    return (
      <HelpCenterLayout auth={auth} title="Không tìm thấy bài viết">
        <LeftSidebar />
        <main className="w-full md:w-3/4 px-4">
          <div className="py-6 bg-white dark:bg-gray-700 shadow-sm sm:rounded-lg">
            <h1 className="text-2xl font-bold">Không tìm thấy bài viết</h1>
            <p className="mt-4">
              Bài viết bạn đang tìm kiếm không tồn tại. Vui lòng quay lại{" "}
              <Link href="/help" className="text-blue-600 hover:underline">
                Trung tâm trợ giúp
              </Link>
              .
            </p>
          </div>
        </main>
      </HelpCenterLayout>
    );
  }

  return (
    <HelpCenterLayout auth={auth} title={article.title}>
      <Head title={`${article.title} - ${category.category}`} />
      <LeftSidebar />
      <main className="w-full md:w-2/4 px-4">
        <div className="py-6 bg-white dark:bg-gray-700 shadow-sm sm:rounded-lg">
          {/* Breadcrumb */}
          <nav className="flex items-center text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">
            <Link href="/help" className="hover:text-gray-700 dark:hover:text-gray-200">
              Trung tâm trợ giúp
            </Link>
            <HiChevronRight className="h-5 w-5 mx-1 flex-shrink-0 text-gray-400" />
            <Link
              href={`/help/${category.slug}/${category.articles[0].slug}`}
              className="hover:text-gray-700 dark:hover:text-gray-200"
            >
              {category.category}
            </Link>
          </nav>

          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">
            {article.title}
          </h1>

          <div
            className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300"
            dangerouslySetInnerHTML={{ __html: article.content }}
          />
        </div>
      </main>
      <TopicList />
    </HelpCenterLayout>
  );
}
