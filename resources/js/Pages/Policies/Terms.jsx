import React from "react";
import DefaultLayout from "@/Layouts/DefaultLayout";

export default function Terms() {
  return (
    <DefaultLayout>
      <div className="container py-6">
        <div className="bg-white dark:bg-[#1e1e1e] rounded-lg p-6 shadow-sm">
          <h1 className="text-3xl font-bold mb-6">ĐIỀU KHOẢN SỬ DỤNG</h1>

          <div className="prose dark:prose-invert max-w-none">
            <p>
              Chào mừng bạn đến với Diễn đàn học sinh Chuyên Biên Hòa. Khi truy cập và sử dụng diễn
              đàn, bạn đồng ý tuân thủ các điều khoản dưới đây. Xin vui lòng đọc kỹ trước khi sử
              dụng dịch vụ.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">1. Chấp nhận điều khoản</h2>

            <p>
              Việc đăng ký tài khoản hoặc sử dụng diễn đàn đồng nghĩa với việc bạn đã đọc, hiểu và
              đồng ý tuân thủ Điều khoản này.
            </p>

            <p>Nếu bạn không đồng ý, vui lòng không sử dụng diễn đàn.</p>

            <h2 className="text-2xl font-bold mt-8 mb-4">2. Đăng ký và sử dụng tài khoản</h2>

            <ul className="list-disc pl-6 mb-4">
              <li>Bạn cần cung cấp thông tin chính xác khi đăng ký tài khoản.</li>
              <li>Bạn chịu trách nhiệm bảo mật mật khẩu và hoạt động trên tài khoản của mình.</li>
              <li>
                Không được sử dụng tài khoản của người khác hoặc chia sẻ tài khoản cho nhiều người.
              </li>
            </ul>

            <h2 className="text-2xl font-bold mt-8 mb-4">3. Nội dung do thành viên đăng tải</h2>

            <ul className="list-disc pl-6 mb-4">
              <li>
                Bạn giữ quyền sở hữu đối với nội dung mình tạo (bài viết, bình luận, hình ảnh, tài
                liệu).
              </li>
              <li>
                Bằng việc đăng tải, bạn đồng ý cho phép diễn đàn lưu trữ, hiển thị và chia sẻ nội
                dung này trong phạm vi cộng đồng.
              </li>
              <li>
                Bạn không được đăng tải nội dung vi phạm pháp luật, nội dung phản cảm, bạo lực, thù
                ghét, quấy rối, hoặc vi phạm bản quyền.
              </li>
            </ul>

            <h2 className="text-2xl font-bold mt-8 mb-4">4. Quy tắc ứng xử</h2>

            <ul className="list-disc pl-6 mb-4">
              <li>Tôn trọng thành viên khác, không công kích cá nhân, không gây chia rẽ.</li>
              <li>Không spam, quảng cáo, hoặc phát tán virus, mã độc.</li>
              <li>Không mạo danh giáo viên, học sinh khác hoặc Ban quản trị.</li>
              <li>Thảo luận, chia sẻ mang tính xây dựng, phù hợp với văn hóa học đường.</li>
            </ul>

            <h2 className="text-2xl font-bold mt-8 mb-4">5. Quyền và trách nhiệm của diễn đàn</h2>

            <ul className="list-disc pl-6 mb-4">
              <li>
                Ban quản trị có quyền kiểm duyệt, chỉnh sửa, xóa bài viết, khóa hoặc xóa tài khoản
                nếu phát hiện vi phạm.
              </li>
              <li>
                Diễn đàn không chịu trách nhiệm đối với nội dung do thành viên đăng tải, nhưng sẽ
                phối hợp xử lý nếu có khiếu nại.
              </li>
              <li>
                Chúng tôi có thể tạm ngưng hoặc chấm dứt dịch vụ bất kỳ lúc nào vì lý do bảo trì, kỹ
                thuật hoặc pháp luật.
              </li>
            </ul>

            <h2 className="text-2xl font-bold mt-8 mb-4">6. Bảo mật và quyền riêng tư</h2>

            <p>
              Việc thu thập và sử dụng dữ liệu cá nhân của bạn được quy định tại{" "}
              <a href={route("policy.privacy")} className="text-[#319527] hover:underline">
                Chính sách bảo mật
              </a>
              .
            </p>

            <p>
              Khi sử dụng diễn đàn, bạn đồng ý rằng dữ liệu có thể được xử lý theo chính sách đó.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">7. Trách nhiệm pháp lý</h2>

            <p>Bạn tự chịu trách nhiệm về nội dung mình đăng và hành vi của mình trên diễn đàn.</p>

            <p>
              Bạn đồng ý bồi thường cho diễn đàn và các bên liên quan nếu hành vi của bạn gây thiệt
              hại, vi phạm pháp luật hoặc quyền lợi người khác.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">8. Sửa đổi điều khoản</h2>

            <p>Điều khoản này có thể được thay đổi, cập nhật bất kỳ lúc nào.</p>

            <p>
              Thông báo sẽ được đăng công khai, và việc tiếp tục sử dụng diễn đàn đồng nghĩa với
              việc bạn chấp nhận điều khoản mới.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">9. Liên hệ</h2>

            <p>Nếu có câu hỏi hoặc yêu cầu liên quan đến Điều khoản sử dụng, vui lòng liên hệ:</p>

            <p className="mt-4">
              <strong>Ban quản trị Diễn đàn học sinh Chuyên Biên Hòa</strong>
              <br />
              Email:{" "}
              <a href="mailto:hotro@chuyenbienhoa.com" className="text-[#319527] hover:underline">
                hotro@chuyenbienhoa.com
              </a>
              <br />
              Hotline:{" "}
              <a href="tel:0365520031" className="text-[#319527] hover:underline">
                0365520031
              </a>
            </p>
          </div>
        </div>
      </div>
    </DefaultLayout>
  );
}
