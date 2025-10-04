import Footer from "@/Components/Footer";
import LeftSidebar from "@/Components/LeftSidebar";
import Navbar from "@/Components/Navbar";
import RightSidebar from "@/Components/RightSidebar";
import BottomCTA from "@/Components/BottomCTA";

export default function HomeLayout({ children, type = "default", activeNav, activeBar }) {
  return (
    <div>
      <Navbar activeNav={activeNav} />
      <div>
        {type == "404" ? (
          <div className="flex-1 mt-[4.3rem]">{children}</div>
        ) : (
          <>
            <div className="flex flex-col xl:flex-row flex-1">
              <LeftSidebar activeBar={activeBar} />
              <div className="flex-1 mt-[4.3rem]">{children}</div>
              <RightSidebar />
            </div>
            <Footer />
          </>
        )}
      </div>
      <BottomCTA type={type} />
    </div>
  );
}
