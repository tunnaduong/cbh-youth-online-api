<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# Thư mời phỏng vấn

Thân gửi bạn **{{ $recipientName }},**

Lời đầu tiên, CYO xin chân thành cảm ơn bạn vì đã dành thời gian, sự quan tâm và yêu mến cho diễn đàn qua đợt tuyển thành viên này. Trong lá thư này, CYO xin thông báo rằng:

**BẠN ĐÃ CHÍNH THỨC VƯỢT QUA VÒNG ĐƠN TUYỂN THÀNH VIÊN**

**{{ $teamName }} CYO GEN 1.0**

Chỉ cần vượt qua Vòng phỏng vấn, bạn sẽ chính thức trở thành một phần của CYO.

Hãy nhớ kỹ những mốc thời gian quan trọng này nhé:

- **Thời gian:** {{ $date }}
- **Hình thức:** {{ $format }}
- **Khung giờ phỏng vấn:** {{ $meetingLink ?? 'Google Meet' }}

> Lưu ý: Bạn vui lòng điền lịch cho vòng Phỏng vấn trước **{{ $deadline }}**. Trong trường hợp bạn vào muộn quá 15 phút hoặc không tham gia Vòng phỏng vấn, chúng mình sẽ loại bạn khỏi danh sách ứng viên.

Chúng mình mong bạn có thể tham gia đúng giờ và chuẩn bị tai nghe/thiết bị có kết nối mạng ổn định để buổi phỏng vấn diễn ra suôn sẻ nhé.

Một lần nữa, CYO cảm ơn bạn đã dành thời gian, sự quan tâm và yêu mến cho bọn mình.

Mọi thắc mắc, vui lòng liên hệ:

- Fanpage: [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline)
- Mail: hotro@chuyenbienhoa.com

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.

@if ($meetingLink)
<x-mail::button :url="$meetingLink">
Tham gia buổi phỏng vấn
</x-mail::button>
@endif
</x-mail::message>
