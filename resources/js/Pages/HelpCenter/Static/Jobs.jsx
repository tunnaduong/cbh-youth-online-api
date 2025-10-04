import React from 'react';
import HelpCenterLayout from '@/Layouts/HelpCenterLayout';
import LeftSidebar from '@/Components/HelpCenter/LeftSidebar';
import { Head } from '@inertiajs/react';

export default function Jobs({ auth }) {
  return (
    <HelpCenterLayout auth={auth} title="Việc làm">
        <Head title="Cơ hội việc làm" />
        <LeftSidebar />
        <main className="w-full md:w-3/4 px-4">
            <div className="p-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">Cơ hội việc làm & Cộng tác</h1>
                <div className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-8">
                    <div>
                        <h2>Cơ hội cộng tác viên nội dung</h2>
                        <p>
                            Bạn là người yêu thích viết lách, sáng tạo và muốn chia sẻ những câu chuyện, kiến thức bổ ích đến với mọi người? Chúng tôi luôn tìm kiếm những cộng tác viên năng động để làm phong phú thêm nội dung cho diễn đàn. Đây là cơ hội tuyệt vời để bạn trau dồi kỹ năng và lan tỏa giá trị tích cực.
                        </p>
                        <p><strong>Yêu cầu:</strong> Có khả năng viết tốt, tinh thần trách nhiệm và đam mê chia sẻ.</p>
                    </div>
                    <div>
                        <h2>Tuyển dụng kỹ thuật viên phát triển web</h2>
                        <p>
                            Nếu bạn có đam mê với công nghệ, lập trình và muốn góp phần xây dựng, phát triển các tính năng mới cho diễn đàn, hãy gia nhập đội ngũ kỹ thuật của chúng tôi. Bạn sẽ được làm việc trong một môi trường năng động và học hỏi từ những người có kinh nghiệm.
                        </p>
                        <p><strong>Yêu cầu:</strong> Có kiến thức cơ bản về HTML, CSS, JavaScript. Kinh nghiệm với React, Laravel là một lợi thế.</p>
                    </div>
                    <div>
                        <h2>Chính sách làm việc và quyền lợi</h2>
                        <p>
                            Chúng tôi mang đến một môi trường làm việc linh hoạt, tôn trọng sự sáng tạo và cân bằng giữa việc học và công việc. Cộng tác viên và thành viên đội ngũ kỹ thuật sẽ nhận được những quyền lợi xứng đáng, cùng cơ hội phát triển bản thân và mở rộng mạng lưới quan hệ.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </HelpCenterLayout>
  );
}