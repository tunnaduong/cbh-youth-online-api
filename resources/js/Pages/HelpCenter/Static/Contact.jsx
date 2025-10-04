import React from "react";
import HelpCenterLayout from "@/Layouts/HelpCenterLayout";
import LeftSidebar from "@/Components/HelpCenter/LeftSidebar";
import { Head } from "@inertiajs/react";

export default function Contact({ auth }) {
  return (
    <HelpCenterLayout auth={auth} title="Liên hệ">
      <Head title="Liên hệ & Hỗ trợ" />
      <LeftSidebar />
      <main className="w-full md:w-3/4 px-4">
        <div className="py-6 bg-white dark:bg-gray-700 shadow-sm sm:rounded-lg">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">
            Liên hệ & Gửi phản hồi
          </h1>
          <div className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
            <p>
              Chúng tôi luôn sẵn lòng lắng nghe ý kiến đóng góp và giải đáp các thắc mắc của bạn.
              Vui lòng sử dụng các thông tin dưới đây để liên hệ với chúng tôi.
            </p>

            <h2>Thông tin liên hệ chung</h2>
            <ul>
              <li>
                <strong>Email hỗ trợ:</strong>{" "}
                <a href="mailto:hotro@chuyenbienhoa.com">hotro@chuyenbienhoa.com</a>
              </li>
              <li>
                <strong>Hotline:</strong> 0365520031 (Trong giờ hành chính)
              </li>
            </ul>

            <h2>Gửi phản hồi hoặc yêu cầu hỗ trợ</h2>
            <p>
              Cách tốt nhất để gửi phản hồi về tính năng, báo cáo lỗi hoặc yêu cầu hỗ trợ kỹ thuật
              là thông qua hệ thống của chúng tôi. Vui lòng truy cập hồ sơ của bạn và sử dụng tính
              năng "Báo cáo sự cố" để đội ngũ kỹ thuật có thể theo dõi và xử lý yêu cầu của bạn một
              cách hiệu quả nhất.
            </p>
            <p>
              Đối với các vấn đề khác, vui lòng gửi email đến địa chỉ hỗ trợ chung. Chúng tôi sẽ cố
              gắng phản hồi trong thời gian sớm nhất.
            </p>
          </div>
        </div>
      </main>
    </HelpCenterLayout>
  );
}
