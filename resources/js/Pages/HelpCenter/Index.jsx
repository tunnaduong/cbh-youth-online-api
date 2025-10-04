import React from 'react';
import { Link } from '@inertiajs/react';
import HelpCenterLayout from '@/Layouts/HelpCenterLayout';
import LeftSidebar from '@/Components/HelpCenter/LeftSidebar';
import { helpArticles } from '@/data/helpArticles';

export default function Index({ auth }) {
  return (
    <HelpCenterLayout auth={auth} title="Trung tâm trợ giúp">
      <LeftSidebar />
      <main className="w-full md:w-3/4 px-4">
        <div className="p-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Chào mừng đến với Trung tâm trợ giúp!</h1>
          <p className="mt-2 text-gray-600 dark:text-gray-300">
            Chúng tôi có thể giúp gì cho bạn? Hãy chọn một chủ đề bên dưới hoặc sử dụng thanh tìm kiếm để tìm câu trả lời bạn cần.
          </p>
          <div className="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {helpArticles.map((category) => (
              <div key={category.slug} className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                <h3 className="font-semibold text-lg text-gray-900 dark:text-gray-100">{category.category}</h3>
                <ul className="mt-2 space-y-2">
                  {category.articles.slice(0, 3).map((article) => (
                    <li key={article.slug}>
                      <Link
                        href={`/help/${category.slug}/${article.slug}`}
                        className="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                      >
                        {article.title}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </main>
    </HelpCenterLayout>
  );
}