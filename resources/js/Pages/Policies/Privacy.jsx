import React from "react";
import DefaultLayout from "@/Layouts/DefaultLayout";

export default function Privacy() {
  return (
    <DefaultLayout>
      <div className="container py-6">
        <div className="bg-white dark:bg-[#1e1e1e] rounded-lg p-6 shadow-sm">
          <h1 className="text-3xl font-bold mb-6">CHÍNH SÁCH BẢO MẬT</h1>

          <div className="prose dark:prose-invert max-w-none">
            <p>
              Diễn đàn học sinh Chuyên Biên Hòa cam kết bảo vệ quyền riêng tư và thông tin cá nhân
              của tất cả thành viên tham gia. Chính sách bảo mật này nhằm giải thích rõ cách chúng
              tôi thu thập, sử dụng, lưu trữ và bảo vệ dữ liệu của bạn khi truy cập và sử dụng diễn
              đàn.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">1. Thông tin chúng tôi thu thập</h2>

            <p>Khi bạn sử dụng diễn đàn, chúng tôi có thể thu thập các loại thông tin sau:</p>

            <p>
              <strong>Thông tin tài khoản:</strong> bao gồm tên đăng nhập, mật khẩu, địa chỉ email,
              số điện thoại (nếu bạn cung cấp).
            </p>

            <p>
              <strong>Thông tin cá nhân:</strong> như họ tên, trường lớp, năm học, ảnh đại diện hoặc
              các dữ liệu mà bạn chủ động chia sẻ trong hồ sơ cá nhân.
            </p>

            <p>
              <strong>Dữ liệu hoạt động:</strong> lịch sử bài viết, bình luận, lượt thích, báo cáo
              vi phạm, tin nhắn riêng tư giữa các thành viên.
            </p>

            <p>
              <strong>Thông tin kỹ thuật:</strong> địa chỉ IP, loại thiết bị, hệ điều hành, trình
              duyệt, cookies và dữ liệu nhật ký truy cập.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">2. Mục đích sử dụng thông tin</h2>

            <p>Thông tin cá nhân của bạn được sử dụng cho các mục đích sau:</p>

            <ul className="list-disc pl-6 mb-4">
              <li>Cung cấp và duy trì dịch vụ của diễn đàn.</li>
              <li>Hỗ trợ xác minh danh tính, quản lý tài khoản, khôi phục mật khẩu.</li>
              <li>Cải thiện trải nghiệm người dùng, đề xuất nội dung phù hợp.</li>
              <li>
                Duy trì an ninh, phát hiện và ngăn chặn hành vi gian lận, spam hoặc vi phạm quy
                định.
              </li>
              <li>
                Liên hệ khi cần thiết (thông báo hệ thống, phản hồi yêu cầu hỗ trợ, cập nhật quy
                định).
              </li>
            </ul>

            <h2 className="text-2xl font-bold mt-8 mb-4">3. Chia sẻ thông tin</h2>

            <p>
              Chúng tôi không bán hoặc trao đổi thông tin cá nhân của bạn cho bên thứ ba vì mục đích
              thương mại. Tuy nhiên, thông tin có thể được chia sẻ trong các trường hợp sau:
            </p>

            <ul className="list-disc pl-6 mb-4">
              <li>
                Theo yêu cầu pháp luật, cơ quan chức năng, hoặc khi có lệnh từ cơ quan có thẩm
                quyền.
              </li>
              <li>
                Khi cần bảo vệ quyền lợi hợp pháp của Diễn đàn, thành viên khác hoặc cộng đồng.
              </li>
              <li>
                Với các đối tác kỹ thuật (ví dụ: dịch vụ lưu trữ, bảo mật, phân tích dữ liệu) nhằm
                duy trì hoạt động của diễn đàn.
              </li>
            </ul>

            <h2 className="text-2xl font-bold mt-8 mb-4">4. Lưu trữ và bảo mật thông tin</h2>

            <p>
              Dữ liệu của bạn được lưu trữ trên hệ thống máy chủ có các biện pháp bảo mật kỹ thuật
              như tường lửa, mã hóa và kiểm soát truy cập.
            </p>

            <p>
              Mật khẩu của bạn được lưu trữ dưới dạng mã hóa, không ai – kể cả quản trị viên – có
              thể xem trực tiếp.
            </p>

            <p>
              Mặc dù chúng tôi nỗ lực bảo mật, nhưng không có hệ thống nào an toàn tuyệt đối. Người
              dùng cần tự bảo vệ thông tin tài khoản của mình bằng cách giữ kín mật khẩu và thoát
              khỏi tài khoản sau khi sử dụng.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">5. Quyền của người dùng</h2>

            <p>Bạn có quyền:</p>

            <ul className="list-disc pl-6 mb-4">
              <li>Xem, chỉnh sửa, cập nhật thông tin cá nhân trong hồ sơ.</li>
              <li>
                Yêu cầu xóa tài khoản hoặc dữ liệu cá nhân khỏi hệ thống (trừ các dữ liệu cần lưu
                giữ để tuân thủ pháp luật hoặc xử lý tranh chấp).
              </li>
              <li>
                Quyết định mức độ công khai thông tin trên diễn đàn (ví dụ: ai có thể xem hồ sơ, bài
                viết, tin nhắn).
              </li>
            </ul>

            <h2 className="text-2xl font-bold mt-8 mb-4">6. Cookie và công nghệ theo dõi</h2>

            <p>
              Diễn đàn có thể sử dụng cookie để lưu thông tin đăng nhập, ghi nhớ tùy chọn và phân
              tích hành vi người dùng.
            </p>

            <p>
              Bạn có thể tắt cookie trong trình duyệt, nhưng điều này có thể làm giảm trải nghiệm sử
              dụng.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">
              7. Chính sách dành cho trẻ vị thành niên
            </h2>

            <p>
              Diễn đàn hướng đến học sinh trung học phổ thông, vì vậy chúng tôi đặc biệt lưu ý đến
              quyền riêng tư của người dưới 18 tuổi.
            </p>

            <p>
              Phụ huynh hoặc giáo viên có thể liên hệ để yêu cầu hỗ trợ quản lý tài khoản học sinh
              nếu cần.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">8. Thay đổi chính sách</h2>

            <p>
              Chính sách này có thể được cập nhật bất kỳ lúc nào nhằm phù hợp với sự thay đổi của
              pháp luật, công nghệ hoặc hoạt động của diễn đàn.
            </p>

            <p>
              Mọi thay đổi quan trọng sẽ được thông báo trên trang chủ hoặc qua email trước khi áp
              dụng.
            </p>

            <p>
              Khi tiếp tục sử dụng diễn đàn sau khi chính sách được cập nhật, bạn được coi là đã
              đồng ý với các điều khoản mới.
            </p>

            <h2 className="text-2xl font-bold mt-8 mb-4">9. Liên hệ</h2>

            <p>Nếu có thắc mắc hoặc yêu cầu liên quan đến quyền riêng tư, vui lòng liên hệ:</p>

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
