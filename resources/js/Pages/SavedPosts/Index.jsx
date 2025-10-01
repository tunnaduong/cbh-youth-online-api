import { useState } from "react";
import HomeLayout from "@/Layouts/HomeLayout";
import { Head, useForm } from "@inertiajs/react";
import { Modal } from "antd";
import SavedPostItem from "./Partials/SavedPostItem";

export default function Index({ savedTopics }) {
  const [searchTerm, setSearchTerm] = useState("");
  const { delete: destroy } = useForm();

  const filteredTopics = savedTopics.filter((topic) =>
    topic.title.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const handleRemove = (topicId) => {
    Modal.confirm({
      title: "Xác nhận bỏ lưu",
      content: "Bạn có chắc chắn muốn bỏ lưu bài viết này không?",
      okText: "Bỏ lưu",
      cancelText: "Hủy",
      okType: "danger",
      onOk() {
        destroy(route("saved.destroy", topicId));
      },
    });
  };

  console.log(savedTopics);

  return (
    <HomeLayout activeNav="home" activeBar="saved">
      <Head title="Đã lưu" />

      <div className="px-3 xl:min-h-screen py-4 md:max-w-[775px] mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">Đã lưu</h1>

        {filteredTopics.length === 0 ? (
          <div className="text-center py-12">
            <p className="text-gray-500 dark:text-gray-400">
              {searchTerm ? "No matching saved posts found" : "No saved posts yet"}
            </p>
          </div>
        ) : (
          <div className="flex flex-col gap-3">
            {filteredTopics.map((topic) => (
              <SavedPostItem key={topic.id} post={topic} onUnsave={handleRemove} />
            ))}
          </div>
        )}
      </div>
    </HomeLayout>
  );
}
