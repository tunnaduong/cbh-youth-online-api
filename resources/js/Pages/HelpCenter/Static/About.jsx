import React from 'react';
import HelpCenterLayout from '@/Layouts/HelpCenterLayout';
import LeftSidebar from '@/Components/HelpCenter/LeftSidebar';
import { Head } from '@inertiajs/react';

export default function About({ auth }) {
  return (
    <HelpCenterLayout auth={auth} title="Giới thiệu">
        <Head title="Giới thiệu" />
        <LeftSidebar />
        <main className="w-full md:w-3/4 px-4">
            <div className="p-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">Về diễn đàn học sinh Chuyên Biên Hòa</h1>
                <div className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                    <p>
                        Chào mừng bạn đến với diễn đàn học sinh Chuyên Biên Hòa - một không gian trực tuyến được tạo ra với mục đích kết nối, chia sẻ và hỗ trợ cộng đồng học sinh của trường.
                    </p>
                    <h2>Tầm nhìn và Sứ mệnh</h2>
                    <p>
                        Sứ mệnh của chúng tôi là xây dựng một môi trường học tập và giao lưu tích cực, nơi mọi học sinh có thể tự do bày tỏ ý kiến, tìm kiếm sự giúp đỡ, và cùng nhau phát triển. Chúng tôi tin rằng bằng cách tạo ra một nền tảng mở, chúng ta có thể thúc đẩy tinh thần đoàn kết và sáng tạo trong cộng đồng.
                    </p>
                    <h2>Câu chuyện phát triển</h2>
                    <p>
                        Dự án này được khởi xướng bởi một nhóm cựu học sinh, những người luôn mang trong mình tình yêu và lòng biết ơn đối với mái trường Chuyên Biên Hòa. Với mong muốn đóng góp lại cho thế hệ đàn em, chúng tôi đã cùng nhau xây dựng nên diễn đàn này như một cầu nối giữa quá khứ, hiện tại và tương lai.
                    </p>
                </div>
            </div>
        </main>
    </HelpCenterLayout>
  );
}