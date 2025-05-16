import LeftSidebar from "@/Components/LeftSidebar";
import Navbar from "@/Components/Navbar";
import RightSidebar from "@/Components/RightSidebar";

export default function HomeLayout({ children }) {
    return (
        <div>
            <Navbar />
            <div className="mt-[4.3rem] flex">
                <LeftSidebar />
                <div className="flex-1">{children}</div>
                <RightSidebar />
            </div>
        </div>
    );
}
