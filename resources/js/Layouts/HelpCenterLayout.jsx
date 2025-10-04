import LeftSidebar from "@/Components/LeftSidebar";
import Navbar from "@/Components/Navbar";
import {
  HelpBuoy,
  InformationCircle,
  DocumentText,
  Search,
  DocumentLock,
  Cash,
  Mail,
} from "react-ionicons";

export default function HelpCenterLayout({ children, activeBar }) {
  return (
    <div>
      <Navbar />
      <div>
        <div className="flex flex-col xl:flex-row flex-1 mt-[4.3rem]">
          <LeftSidebar
            activeBar={activeBar}
            type="help"
            items={[
              {
                key: "help",
                href: "/help",
                label: "Hỗ trợ",
                Icon: HelpBuoy,
              },
              {
                key: "about",
                href: "/about",
                label: "Giới thiệu",
                Icon: InformationCircle,
              },
              {
                key: "terms",
                href: "/terms",
                label: "Điều khoản",
                Icon: DocumentText,
              },
              {
                key: "careers",
                href: "/careers",
                label: "Việc làm",
                Icon: Search,
              },
              {
                key: "privacy",
                href: "/privacy",
                label: "Quyền riêng tư",
                Icon: DocumentLock,
              },
              {
                key: "ads",
                href: "/ads",
                label: "Quảng cáo",
                Icon: Cash,
              },
              {
                key: "contact",
                href: "/contact",
                label: "Liên hệ",
                Icon: Mail,
              },
            ]}
          />
          <div className="flex-1">
            {children}
            <div className="px-3 mt-10">
              <div className="container-footer mx-auto py-6">
                <hr className="mb-4" />
                <div className="flex items-center justify-between">
                  <a href="https://fatties.vercel.app" target="_blank">
                    <img
                      src="/images/from_fatties.png"
                      alt="Fatties Logo"
                      className="h-6 w-auto -ml-0.5"
                    />
                  </a>
                  <div className="ml-4 text-[12px] text-gray-500">© 2025 Fatties Software</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
