<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# Thông báo kết quả tuyển thành viên

Thân gửi bạn **{{ $recipientName }},**

Lời đầu tiên, CYO xin chân thành cảm ơn bạn vì đã dành thời gian, sự quan tâm và yêu mến cho diễn đàn qua đợt tuyển thành viên này. Sau khi xem xét kỹ lưỡng, CYO xin thông báo rằng:

@if ($status === 'accepted')
**BẠN ĐÃ CHÍNH THỨC TRỞ THÀNH THÀNH VIÊN GEN 1.0**

**CỦA DIỄN ĐÀN HỌC SINH CHUYÊN BIÊN HÒA - CYO**

Một lần nữa, CYO cảm ơn bạn vì đã lựa chọn thành một phần của chúng mình.

Bây giờ, bạn hãy tham gia vào nhóm Messenger để đồng hành cùng Diễn đàn nhé.

Chúng mình tin rằng từ những con người như bạn sẽ góp phần tạo nên một sân chơi văn minh, an toàn cho học sinh trường THPT Chuyên Biên Hòa.

Chúc bạn có một khoảng thời gian đáng nhớ, được trải nghiệm, học hỏi nhiều điều bổ ích trong một năm tới cùng CBH Youth Online.
@else
**BẠN ĐÃ KHÔNG TRỞ THÀNH THÀNH VIÊN GEN 1.0**

**CỦA DIỄN ĐÀN HỌC SINH CHUYÊN BIÊN HÒA - CYO**

Chúng mình rất lấy làm tiếc khi không thể đồng hành cùng bạn trong khoảng thời gian sắp tới. Cảm ơn bạn vì đã dành thời gian, sự quan tâm và yêu mến khi tham gia xét tuyển. CYO hy vọng bạn vẫn sẽ lựa chọn đồng hành cùng Diễn đàn trong những đợt tuyển thành viên tiếp theo.
@endif

Mọi thắc mắc, vui lòng liên hệ:

- Fanpage: [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline)
- Mail: hotro@chuyenbienhoa.com

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.
</x-mail::message>
