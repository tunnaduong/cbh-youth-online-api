import { useState } from "react";
import HomeLayout from "@/Layouts/HomeLayout";
import { Head, Link, useForm } from "@inertiajs/react";

export default function Index({ savedTopics }) {
  const [searchTerm, setSearchTerm] = useState("");
  const { delete: destroy } = useForm();

  const filteredTopics = savedTopics.filter((topic) =>
    topic.title.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const handleRemove = (topicId) => {
    if (confirm("Are you sure you want to remove this post from saved items?")) {
      destroy(route("saved.destroy", topicId));
    }
  };

  return (
    <HomeLayout activeNav="home" activeBar="saved">
      <Head title="Saved Posts" />

      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div className="px-4 py-5 sm:p-6">
              <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Saved Posts</h1>
                <div className="relative">
                  <input
                    type="text"
                    placeholder="Search saved posts..."
                    className="w-64 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                  />
                </div>
              </div>

              {filteredTopics.length === 0 ? (
                <div className="text-center py-12">
                  <p className="text-gray-500 dark:text-gray-400">
                    {searchTerm ? "No matching saved posts found" : "No saved posts yet"}
                  </p>
                </div>
              ) : (
                <div className="space-y-4">
                  {filteredTopics.map((topic) => (
                    <div
                      key={topic.id}
                      className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                    >
                      <div className="flex justify-between items-start">
                        <div className="flex-1">
                          <Link
                            href={route("posts.show", {
                              username: topic.author.username,
                              id: topic.id,
                            })}
                            className="text-lg font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400"
                          >
                            {topic.title}
                          </Link>
                          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {topic.description.length > 200
                              ? topic.description.substring(0, 200) + "..."
                              : topic.description}
                          </p>
                          <div className="mt-2 flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                            <span>
                              By{" "}
                              {topic.anonymous
                                ? "Người dùng ẩn danh"
                                : topic.author.profile_name || topic.author.username}
                            </span>
                            <span>•</span>
                            <span>{topic.created_at}</span>
                            <span>•</span>
                            <span>{topic.stats.views} views</span>
                            <span>•</span>
                            <span>{topic.stats.comments} comments</span>
                          </div>
                        </div>
                        <button
                          onClick={() => handleRemove(topic.id)}
                          className="ml-4 text-gray-400 hover:text-red-500 dark:hover:text-red-400"
                        >
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            className="h-5 w-5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                          >
                            <path
                              fillRule="evenodd"
                              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                              clipRule="evenodd"
                            />
                          </svg>
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
