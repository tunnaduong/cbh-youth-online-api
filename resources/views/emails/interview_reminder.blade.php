<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# Nhắc lịch phỏng vấn

Thân gửi bạn **{{ $recipientName }},**

Lời đầu tiên, CYO xin chân thành cảm ơn bạn vì đã dành thời gian, sự quan tâm và yêu mến cho diễn đàn qua đợt tuyển thành viên này. Trong lá thư này, CYO xin phép được nhắc nhở rằng:

**Đã đến lúc điền lịch Vòng phỏng vấn trước khi quá thời hạn, chỉ cần vượt qua Vòng phỏng vấn bạn sẽ chính thức trở thành một phần của CYO.**

Hãy nhớ kỹ những mốc thời gian quan trọng này nhé:

- **Thời gian:** {{ $date }}
- **Hình thức:** {{ $format }}
- **Khung giờ phỏng vấn:** [{{ $scheduleLink }}]({{ $scheduleLink }})

> **Lưu ý:** Bạn vui lòng điền lịch cho vòng Phỏng vấn và tham gia vào Discord Chuyên Biên Hòa Online: [{{ $discordLink }}]({{ $discordLink }}) trước **{{ $deadline }}**. Trong trường hợp bạn vào muộn quá 15 phút hoặc không tham gia Vòng phỏng vấn, chúng mình sẽ loại bạn khỏi danh sách ứng viên.

Chúng mình mong bạn có thể tham gia đúng giờ và chuẩn bị tai nghe/thiết bị có kết nối mạng ổn định để buổi phỏng vấn diễn ra suôn sẻ nhé.

Một lần nữa, CYO cảm ơn bạn đã dành thời gian, sự quan tâm và yêu mến cho bọn mình.

Nếu bạn đã điền xong thông tin, bạn có thể bỏ qua email này. Nếu không thể xếp được thời gian để phỏng vấn hay có vấn đề gì khác, hãy liên hệ [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline) qua Messenger trước **{{ $contactDeadline }}** để được giúp đỡ.

Mọi thắc mắc, vui lòng liên hệ:

- Fanpage: [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline)
- Mail: hotro@chuyenbienhoa.com

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.

<x-mail::button :url="$scheduleLink">
Điền lịch phỏng vấn
</x-mail::button>
</x-mail::message>
