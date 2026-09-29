# Spec: Tùy chỉnh giao diện trang cá nhân (Profile Theme)

> Thành viên trang trí trang cá nhân. Trình chỉnh sửa mô phỏng trang Profiles của Discord (cả phần free lẫn Nitro), nhưng **mở khóa bằng điểm hoạt động** thay vì trả tiền. Không có Nitro, Shop hay Wishlist.
> Phạm vi: **web** (`cbh-youth-online-next-js`) và **API** (`cbh-youth-online-api`). Không bao gồm app mobile.

- Trạng thái: **đã code, chưa commit**
- Cập nhật: 2026-09-29

---

## 1. Tính năng

| Discord | Bên mình | Ghi chú |
|---|---|---|
| Avatar & Decoration | Ảnh đại diện + **khung avatar** (6 khung) | Upload avatar ngay trong trình chỉnh sửa |
| Animated Avatar | **Avatar GIF động** | Chỉ hạng 1000 điểm |
| Banner Color | **Màu ảnh bìa** | Dùng khi chưa có ảnh bìa |
| Profile Banner | **Ảnh bìa** | Upload ngay trong trình chỉnh sửa |
| Profile Effect | **Hiệu ứng hồ sơ** (4 hiệu ứng) | Chạy ~6 giây mỗi lần mở trang rồi mờ dần |
| Profile Frame | **Khung hồ sơ** (3 khung) | Viền quanh ảnh bìa/thẻ profile |
| Display Name Style | **Kiểu tên**: 12 phông + 6 hiệu ứng + màu tên | Phông Google có tiếng Việt |
| Profile Theme | **Màu giao diện** (màu chính, màu phụ) | Tùy chọn; chưa chọn thì trang giữ màu mặc định |
| Nameplate | Chưa làm | |
| Nitro, Shop, Wishlist | Không làm | |

## 2. Mở khóa theo điểm

Tùy chỉnh được khi đạt **Thành viên tập sự (50 điểm)** (quyền `custom_profile`). Tùy chọn càng đẹp thì cần hạng càng cao. Hạng tính theo **điểm hiện tại**.

| Hạng | Điểm | Mở khóa thêm |
|---|---|---|
| Thành viên tập sự | 50 | Màu giao diện, màu ảnh bìa · Phông: Mặc định, Cao gọn, Hiện đại, Tròn trịa, Viết tay · Hiệu ứng tên: Một màu · Khung avatar: Theo màu, Bạc |
| Thành viên tích cực | 150 | Phông: Thư pháp, Truyện tranh, Pixel, Công nghệ · Hiệu ứng tên: Chuyển màu, Nổi khối · Khung avatar: Băng · Hiệu ứng hồ sơ: Lấp lánh, Trái tim · Khung hồ sơ: Phát sáng |
| Thành viên tiêu biểu | 500 | Phông: Cổ điển, Đậm chất, Gai góc · Hiệu ứng tên: Hoạt hình, Neon · Khung avatar: Vàng · Hiệu ứng hồ sơ: Tuyết rơi · Khung hồ sơ: Viền vàng |
| Thành viên kỳ cựu | 1000 | Khung avatar: Cầu vồng (xoay) · Hiệu ứng hồ sơ: Cực quang · Khung hồ sơ: Neon xoay · Avatar GIF động |

Bảng này nằm ở `ProfileThemeService::OPTIONS` (API). Đổi mốc chỉ cần sửa ở đó; trình chỉnh sửa và cột "Mốc điểm" tự đọc từ API.

### Quy tắc
1. **Tụt hạng:** theme đã lưu giữ nguyên trong DB. Người khác chỉ thấy các tùy chọn còn đủ điểm, phần còn lại về mặc định. Đủ điểm lại thì tự hiện lại. Dưới 50 điểm thì cả trang về giao diện mặc định.
2. **Xem thử khi chưa đủ điểm** (thay cho "Preview Nitro"): mọi tùy chọn đều chọn và xem trước được; ô chưa mở khóa có nhãn 🔒 + số điểm; nút Lưu bị khóa và thanh đáy báo "Đang xem thử — cần X điểm để lưu".
3. **Khôi phục mặc định** (`profile_theme: null`) luôn được phép.
4. Chỉ chủ trang sửa được theme của mình (403 nếu sửa người khác).
5. Sửa bio/tên mà không gửi `profile_theme` thì theme giữ nguyên.
6. Không nhận CSS tự do: màu phải là hex 6 ký tự, các tùy chọn phải nằm trong danh sách.
7. **Avatar GIF động:** chỉ hạng 1000 điểm, file ≤ 2MB, gần vuông (lệch ≤ 10%), được lưu nguyên file để giữ animation. Người dưới hạng upload GIF thì vẫn bị chuyển sang JPEG 156px như cũ (mất animation).

## 3. Giao diện

### 3.1. Trình chỉnh sửa (`/settings/appearance`)
Trang riêng, vào từ: nút bảng màu cạnh "Sửa hồ sơ" trên profile, hoặc mục "Giao diện hồ sơ" trong Cài đặt.

Bố cục như Discord: từ 1280px trở lên chia 3 cột (các mục chỉnh | preview | Mốc điểm); hẹp hơn thì preview và Mốc điểm xếp dọc ở cột thứ hai, không dính khi cuộn để không chồng lên nhau.
- **Trái – các mục chỉnh**, mỗi mục là các ô lớn:
  - *Ảnh đại diện & Khung*: ô avatar (bấm để upload) và ô khung (mở lưới chọn khung). Dòng nhỏ "GIF động · 1000 điểm".
  - *Ảnh bìa*: ô màu ảnh bìa và ô ảnh bìa (bấm để upload).
  - *Hiệu ứng & Khung hồ sơ*: hai ô, mỗi ô mở lưới chọn có xem trước động.
  - *Kiểu tên*: ô hiện tên đang chọn, mở modal Phông chữ / Hiệu ứng / Màu.
  - *Màu giao diện*: hai ô màu, nút "Bỏ màu giao diện".
  - Nút "Khôi phục mặc định".
- **Giữa – thẻ profile** (rộng 400px) cập nhật ngay: ảnh bìa, avatar có khung, tên, @username, giới thiệu, ngày tham gia, điểm, hiệu ứng và khung hồ sơ, và dòng "Trong bình luận" (tên bản rút gọn).
- **Phải – Mốc điểm** (dạng trực quan, ít chữ):
  - Thanh tiến trình với 4 mốc cách đều (50 / 150 / 500 / 1000), mỗi mốc là huy hiệu hạng; mốc chưa đạt hiện xám.
  - Dòng "Còn X điểm tới <hạng kế tiếp>".
  - Mỗi hạng là một thẻ: tên + huy hiệu, nhãn điểm (✓ nếu đã đạt, 🔒 nếu chưa), và một hàng **ô minh họa nhỏ** cho từng thứ được mở khóa (khung avatar vẽ trên avatar của user, "Aa" theo phông/hiệu ứng, biểu tượng hiệu ứng hồ sơ, khung hồ sơ thu nhỏ, ô màu, "GIF"). Rê chuột để xem tên, **bấm để thử ngay trên preview**.
- **Thanh đáy** khi có thay đổi: "Đừng quên lưu thay đổi!" + Đặt lại / Lưu. Khi còn thay đổi chưa lưu: bấm link bất kỳ trong trang thì bị chặn, thanh đỏ lên và rung; đóng/tải lại tab thì trình duyệt hỏi xác nhận.
- Upload avatar/ảnh bìa lưu **ngay** (như trước đây), không cần bấm Lưu. Các tùy chọn còn lại chỉ lưu khi bấm Lưu.
- Khi mở trang, các tùy chọn đã lưu nhưng không còn đủ điểm được bỏ khỏi bản nháp, để thanh đáy chỉ phản ánh thay đổi mới.

### 3.2. Trang cá nhân (`/{username}`)
- Ảnh bìa: ảnh bìa nếu có; nếu không thì màu ảnh bìa; nếu không thì gradient màu giao diện; nếu không thì như cũ.
- Hiệu ứng hồ sơ chạy trên ảnh bìa khi mở trang; khung hồ sơ viền quanh ảnh bìa.
- Màu giao diện phủ nhẹ thanh thống kê / thẻ thông tin và tô gạch chân tab.
- Avatar có khung, tên có phông + hiệu ứng.
- Mọi animation tắt khi hệ điều hành bật "giảm chuyển động" (`prefers-reduced-motion`).

### 3.3. Tên trong bài viết/bình luận
Component `StyledName` có bản `compact`: chỉ hiện phông, rê chuột mới hiện hiệu ứng (như Discord trong tin nhắn). **Chưa gắn** vào bài viết/bình luận.

## 4. Kỹ thuật

### 4.1. Dữ liệu
Cột `cyo_user_profiles.profile_theme` (JSON, nullable), migration `2026_09_27_000001_add_profile_theme_to_cyo_user_profiles_table.php`. Luôn được chuẩn hóa đủ key:

```json
{
  "primary_color": "#7c3aed",
  "accent_color": null,
  "banner_color": "#4c1d95",
  "name_font": "heavy",
  "name_effect": "gradient",
  "name_colors": ["#f97316", "#db2777"],
  "avatar_frame": "veteran",
  "profile_effect": "sparkles",
  "profile_frame": "neon"
}
```

### 4.2. API
- `GET /v1.0/users/{username}/profile` trả thêm `profile.theme` (bản mọi người thấy, hoặc `null`) và `profile.theme_editor` (chỉ chủ trang): `can_customize`, `required_points`, `current_points`, `tiers[]`, `animated_avatar`, `saved`, `options{field: [{key, required_points, unlocked}]}`.
- `PUT /v1.0/users/{username}/profile` nhận `profile_theme` (object hoặc `null`): 200 hợp lệ · 422 sai định dạng hoặc chọn mục chưa đủ điểm ("Tùy chọn này cần đạt X điểm.") · 403 dưới 50 điểm hoặc sửa người khác.
- `POST /v1.0/users/{username}/avatar`: nhánh GIF động cho hạng 1000 (`storeAnimatedAvatar`).
- Logic: `app/Services/ProfileThemeService.php`.

### 4.3. Web
| File | Vai trò |
|---|---|
| `src/app/settings/appearance/` | Trang trình chỉnh sửa |
| `src/components/profile/ProfileCustomizer.js` | Bố cục 3 cột, bản nháp, lưu, thanh đáy, chặn rời trang, upload |
| `src/components/profile/ProfilePreviewCard.js` | Thẻ profile lớn ở giữa |
| `src/components/profile/PointsMilestones.js` | Cột Mốc điểm |
| `src/components/profile/OptionPickerModal.js` | Lưới chọn (khung avatar, hiệu ứng, khung hồ sơ) |
| `src/components/profile/NameStyleModal.js` | Modal kiểu tên |
| `src/components/profile/AvatarFrame.js`, `UserAvatar.js` | Khung avatar |
| `src/components/profile/ProfileEffect.js` | Hiệu ứng hồ sơ |
| `src/components/profile/ProfileFrame.js` | Khung hồ sơ |
| `src/components/profile/StyledName.js` | Tên theo phông + hiệu ứng (`full` / `compact`) |
| `src/lib/profileTheme.js` | Chuẩn hóa theme, màu, khung avatar, nhãn tiếng Việt |
| `src/lib/nameFonts.js` | 11 phông nạp bằng `next/font/google` |
| `src/app/globals.css` | Các animation |

### 4.4. Thêm tùy chọn mới
1. **API:** thêm key vào `ProfileThemeService::OPTIONS[field]` kèm hạng cần có.
2. **Web:** vẽ nó (khung avatar: `AVATAR_FRAMES`; hiệu ứng/khung hồ sơ: `ProfileEffect` / `ProfileFrame`; phông: `nameFonts.js`, chỉ dùng phông có subset `vietnamese`, tham số `next/font` phải viết trực tiếp) và thêm nhãn vào `OPTION_LABELS`.
3. Khung avatar dạng ảnh (ChatGPT + Canva): `{ type: "image", src }`, ảnh vuông 512×512, lỗ tròn giữa ~80%, nền trong suốt, WebP < 100KB, đặt trong `public/profile-frames/`.

> ⚠️ Key ở API và web phải khớp nhau.

## 5. Kiểm thử
| Loại | Kết quả |
|---|---|
| Unit test `tests/Unit/ProfileThemeServiceTest.php` | 11/11 pass (54 assertion): validate, chuẩn hóa, mở khóa theo hạng, tụt hạng, dữ liệu hỏng/dạng cũ, avatar động, dữ liệu trình chỉnh sửa |
| Trình duyệt (Chrome headless), 1000 điểm | Chọn khung Cầu vồng, hiệu ứng Lấp lánh, khung Neon xoay, phông Đậm chất + Chuyển màu, màu ảnh bìa, màu chính → lưu 200 với đúng dữ liệu; bấm link khi chưa lưu bị chặn; trang cá nhân hiện đủ |
| Trình duyệt, 150 điểm | Trang cá nhân tự bỏ khung Cầu vồng/Neon xoay/phông Đậm chất, giữ Lấp lánh + Chuyển màu; ô 500/1000 có nhãn khóa; chọn khung Vàng → "cần 500 điểm", nút Lưu khóa; bố cục màn hình hẹp đúng; không lỗi JS |
| Chưa kiểm tra | Upload avatar GIF động thật, upload ảnh bìa từ trình chỉnh sửa (dùng lại API có sẵn) |

## 6. Còn lại
- [ ] Commit trên nhánh riêng (đang lẫn với thay đổi dở trên `feat/composer-page` và `feat/giftshop-shop-support-chat`).
- [ ] Chạy migration trên staging/production.
- [ ] Feature test HTTP cho `GET/PUT /profile` và upload avatar GIF.
- [ ] Gắn khung avatar + tên `compact` vào bài viết, bình luận, bảng xếp hạng (API cần trả `theme` trong payload tác giả, `TopicsController` 4 chỗ).
- [ ] Nameplate (khi cần).
- [ ] Bộ ảnh khung/hiệu ứng làm bằng ChatGPT + Canva thay cho bản CSS.
- [ ] Hiệu ứng tên Gummy, Prism.

### Lỗi có sẵn phát hiện khi test (không thuộc tính năng này)
- `src/components/forum/PostItem.js:153` gọi `document.createElement` khi render phía server → trang profile lỗi 500 với user có bài viết (dev local).
- Ảnh avatar/ảnh bìa không tải được trên môi trường local.
