import React from 'react';
import HelpCenterLayout from '@/Layouts/HelpCenterLayout';
import LeftSidebar from '@/Components/HelpCenter/LeftSidebar';
import { Head } from '@inertiajs/react';

export default function Ads({ auth }) {
  return (
    <HelpCenterLayout auth={auth} title="Quảng cáo">
        <Head title="Quảng cáo trên diễn đàn" />
        <LeftSidebar />
        <main className="w-full md:w-3/4 px-4">
            <div className="p-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">Quảng cáo trên diễn đàn</h1>
                <div className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-8">
                    <div>
                        <h2>Hướng dẫn quảng cáo</h2>
                        <p>
                            Diễn đàn của chúng tôi là một kênh hiệu quả để tiếp cận cộng đồng học sinh năng động. Chúng tôi cung cấp nhiều vị trí và định dạng quảng cáo khác nhau để phù hợp với nhu cầu của bạn. Vui lòng liên hệ với bộ phận kinh doanh qua email <a href="mailto:ads@dien-dan-cbh.com">ads@dien-dan-cbh.com</a> để nhận báo giá và tư vấn chi tiết.
                        </p>
                    </div>
                    <div>
                        <h2>Quy định về nội dung quảng cáo</h2>
                        <p>
                            Mọi nội dung quảng cáo phải tuân thủ pháp luật Việt Nam và phù hợp với môi trường học đường. Chúng tôi không chấp nhận các quảng cáo liên quan đến rượu bia, thuốc lá, nội dung người lớn, cờ bạc hoặc các sản phẩm/dịch vụ bất hợp pháp. Ban quản trị có quyền từ chối bất kỳ quảng cáo nào không phù hợp.
                        </p>
                    </div>
                    <div>
                        <h2>Chính sách thanh toán</h2>
                        <p>
                            Chúng tôi chấp nhận thanh toán qua chuyển khoản ngân hàng và các ví điện tử phổ biến như MoMo, ZaloPay. Hợp đồng quảng cáo sẽ được kích hoạt sau khi chúng tôi xác nhận thanh toán thành công.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </HelpCenterLayout>
  );
}