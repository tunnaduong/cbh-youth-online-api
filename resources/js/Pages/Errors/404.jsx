import { Link } from "@inertiajs/react";

export default function Error404() {
    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-100 dark:bg-neutral-800">
            <div className="text-center">
                <h1 className="text-9xl font-bold text-gray-800 dark:text-gray-200">
                    404
                </h1>
                <h2 className="text-2xl font-semibold text-gray-700 dark:text-gray-300 mt-4">
                    Không tìm thấy trang
                </h2>
                <p className="text-gray-600 dark:text-gray-400 mt-2">
                    Trang bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển.
                </p>
                <Link
                    href="/"
                    className="inline-block mt-8 px-6 py-3 bg-[#319527] hover:bg-[#3dbb31] text-white rounded-lg transition-colors duration-200"
                >
                    Trở về trang chủ
                </Link>
            </div>
        </div>
    );
}
