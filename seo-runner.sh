#!/bin/zsh

# Script để chạy lệnh SEO với số tăng dần
# Sử dụng: ./seo-runner.sh [--type=cat|sub]
# Mặc định: --type=cat

# Đặt giá trị mặc định
TYPE="cat"

# Xử lý tham số đầu vào
while [[ $# -gt 0 ]]; do
    case $1 in
        --type=*)
            TYPE="${1#*=}"
            shift
            ;;
        *)
            echo "Tham số không hợp lệ: $1"
            echo "Sử dụng: $0 [--type=cat|sub]"
            exit 1
            ;;
    esac
done

# Kiểm tra giá trị TYPE hợp lệ
if [[ "$TYPE" != "cat" && "$TYPE" != "sub" ]]; then
    echo "Lỗi: --type phải là 'cat' hoặc 'sub'"
    echo "Sử dụng: $0 [--type=cat|sub]"
    exit 1
fi

# Xác định tham số ID dựa trên type
if [[ "$TYPE" == "cat" ]]; then
    ID_PARAM="--catid"
else
    ID_PARAM="--subid"
fi

echo "🚀 Bắt đầu chạy SEO runner với type: $TYPE"
echo "📝 Mỗi lần chạy, paste nội dung SEO description từ ChatGPT vào clipboard trước"
echo "⏸️  Nhấn Enter để tiếp tục sau mỗi lệnh, hoặc Ctrl+C để dừng"
echo ""

# Bắt tín hiệu Ctrl+C để thoát gracefully
trap 'echo -e "\n\n👋 Đã dừng SEO runner. Tạm biệt!"; exit 0' INT

# Bắt đầu từ số 1 và chạy vô hạn
counter=1

while true; do
    echo "🔄 Đang chạy lệnh với ID=$counter (type=$TYPE)"
    echo "📋 Đảm bảo bạn đã copy nội dung SEO description vào clipboard"
    echo "▶️  Nhấn Enter để thực thi lệnh..."
    read
    
    echo "🚀 Thực thi: pbpaste | php artisan seo: $ID_PARAM=$counter --force"
    pbpaste | php artisan seo: $ID_PARAM=$counter --force
    
    echo ""
    echo "✅ Hoàn thành ID=$counter"
    echo "⏭️  Nhấn Enter để tiếp tục với ID=$((counter + 1)), hoặc Ctrl+C để dừng..."
    read
    
    ((counter++))
    echo ""
done