import { Head } from "@inertiajs/react";
import HomeLayout from "@/Layouts/HomeLayout";
import ViewTrackingDemo from "@/Components/ViewTrackingDemo";

export default function ViewTrackingTest() {
  const demoPosts = [
    { id: 1, title: "Bài viết demo 1 - Test view tracking" },
    { id: 2, title: "Bài viết demo 2 - Cuộn để xem" },
    { id: 3, title: "Bài viết demo 3 - Intersection Observer" },
    { id: 4, title: "Bài viết demo 4 - Auto view tracking" },
    { id: 5, title: "Bài viết demo 5 - Scroll để test" },
  ];

  return (
    <HomeLayout activeNav="home">
      <Head title="View Tracking Test" />

      <div className="px-1 xl:min-h-screen pt-4 md:max-w-[775px] mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6 px-1">
          Test View Tracking
        </h1>

        <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <h2 className="text-lg font-semibold text-blue-900 mb-2">Hướng dẫn test:</h2>
          <ul className="text-blue-800 text-sm space-y-1">
            <li>• Cuộn xuống để xem các bài viết demo</li>
            <li>• Khi 50% bài viết hiển thị, sẽ đếm ngay lập tức</li>
            <li>• Màu xanh lá cây = đã được xem (hiển thị số lần xem)</li>
            <li>• Màu xám = chưa được xem</li>
            <li>• Cuộn lên xuống nhiều lần để test đếm nhiều lần</li>
            <li>• Không có cooldown - đếm ngay khi visible</li>
            <li>• Hoạt động cho cả người dùng chưa đăng nhập</li>
            <li>• Kiểm tra Network tab để xem API calls</li>
          </ul>
        </div>

        <div className="space-y-4">
          {demoPosts.map((post) => (
            <ViewTrackingDemo key={post.id} postId={post.id} title={post.title} />
          ))}
        </div>

        <div className="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <h3 className="text-lg font-semibold text-yellow-900 mb-2">Lưu ý:</h3>
          <p className="text-yellow-800 text-sm">
            Tính năng này sẽ tự động tăng lượt xem khi người dùng cuộn và xem bài viết trong bảng
            tin, tin tức đoàn hoặc khi bấm vào xem chi tiết. Hoạt động cho cả người dùng chưa đăng
            nhập. Mỗi lần cuộn qua bài viết sẽ đếm ngay lập tức (không cooldown).
          </p>
        </div>
      </div>
    </HomeLayout>
  );
}
