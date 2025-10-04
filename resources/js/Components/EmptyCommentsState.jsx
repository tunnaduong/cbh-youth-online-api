import { FaRegCommentAlt } from "react-icons/fa";
import { useMemo } from "react";

const funMessages = [
  "Chưa có ai để lại lời nhắn… Hãy là người đầu tiên nhé!",
  "Trang này còn trống lắm, bình luận của bạn sẽ làm nó sống động hơn.",
  "Im lặng quá! Thêm bình luận để phá vỡ sự tĩnh lặng đi nào.",
  "Không có gì để đọc… ngoại trừ bình luận đầu tiên của bạn.",
  "Bạn thấy trống trơn phải không? Hãy là người khai phá đầu tiên!",
  "Nơi này chưa có drama nào cả… bạn muốn tạo drama không?",
  "Chưa ai lên tiếng… ai sẽ là người dũng cảm đầu tiên?",
  "Trống trơn như tờ giấy… hãy viết lên đây điều gì đó thú vị!",
  "Ai đó đã nói 'Hãy comment đi!'… và đó là bạn.",
  "Chưa có bình luận nào. Đây là cơ hội của bạn để tỏa sáng!",
  "Oops… nơi này trống như tủ lạnh vào cuối tháng.",
  "404: Bình luận không tìm thấy. Bạn có muốn tạo ra một cái không?",
  "Không có bình luận nào. Hãy để dấu ấn meme đầu tiên của bạn!",
  "Nơi này yên tĩnh như một thư viện vào Chủ Nhật.",
  "Cảnh báo: Chưa có bình luận nào. Có nguy cơ buồn chán cao.",
  "Hãy là người đầu tiên để nói 'Xin chào!' với mọi người.",
  "Bình luận đầu tiên là bước khởi đầu tuyệt vời, bạn có muốn thử không?",
  "Mọi người đang chờ nghe suy nghĩ của bạn!",
  "Đừng ngần ngại, bạn có thể là ngôi sao bình luận đầu tiên!",
  "Một comment nhỏ có thể tạo ra niềm vui lớn!",
  "Cái ghế comment này đang trống… bạn có muốn ngồi không?",
  "Nơi này còn trống như tâm hồn sáng nay… hãy lấp đầy nó!",
  "Không có bình luận… nhưng bạn có thể thay đổi điều đó ngay bây giờ.",
  "Ai đó phải comment để sống… và đó là bạn!",
  "Trang comment đang đói… cho nó ăn một comment đi!",
  "Comment đi, để tôi không phải nói chuyện một mình!",
  "Hãy comment đi, chúng tôi hứa sẽ không đánh giá bạn (nhiều lắm)!",
  "Nơi này trống rỗng, giống như tủ quần áo mùa hè… hãy thêm màu sắc với comment của bạn.",
  "Ai đó phải phá vỡ sự tĩnh lặng… chắc chắn là bạn rồi!",
  "Chưa có bình luận nào, nhưng có thể bạn sẽ làm cả thế giới cười!",
];

export default function EmptyCommentsState() {
  // Get a random fun message - memoized to prevent re-randomization on re-renders
  const randomMessage = useMemo(() => {
    return funMessages[Math.floor(Math.random() * funMessages.length)];
  }, []);

  return (
    <div className="flex flex-col items-center justify-center py-12 px-6 text-center">
      {/* Large comment icon */}
      <div className="mb-6">
        <FaRegCommentAlt size={64} className="text-gray-400 dark:text-gray-500" />
      </div>

      {/* Fun message */}
      <p className="text-gray-600 dark:text-gray-400 text-lg max-w-md leading-relaxed">
        {randomMessage}
      </p>
    </div>
  );
}
