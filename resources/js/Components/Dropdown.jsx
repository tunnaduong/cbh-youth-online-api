import { useState, createContext, useContext, Fragment } from "react";
import { Link } from "@inertiajs/react";
import { Transition } from "@headlessui/react";

const DropDownContext = createContext();

const Dropdown = ({ children }) => {
  const [open, setOpen] = useState(false);

  const toggleOpen = () => {
    setOpen((previousState) => !previousState);
  };

  return (
    <DropDownContext.Provider value={{ open, setOpen, toggleOpen }}>
      <div className="relative">{children}</div>
    </DropDownContext.Provider>
  );
};

const Trigger = ({ children }) => {
  const { open, setOpen, toggleOpen } = useContext(DropDownContext);

  return (
    <>
      <div onClick={toggleOpen}>{children}</div>

      {open && <div className="fixed inset-0 z-40" onClick={() => setOpen(false)}></div>}
    </>
  );
};

const Content = ({
  align = "right",
  width = "48",
  contentClasses = "py-1 bg-white dark:!bg-gray-700",
  children,
}) => {
  const { open, setOpen } = useContext(DropDownContext);

  let alignmentClasses = "origin-top";

  if (align === "left") {
    alignmentClasses = "ltr:origin-top-left rtl:origin-top-right start-0";
  } else if (align === "right") {
    alignmentClasses = "ltr:origin-top-right rtl:origin-top-left end-0";
  } else if (align === "responsive") {
    // Responsive alignment: left on small screens, right on larger screens
    alignmentClasses =
      "ltr:origin-top-left rtl:origin-top-right start-0 sm:ltr:origin-top-right sm:rtl:origin-top-left sm:end-0 sm:start-auto";
  }

  let widthClasses = "";

  if (width === "48") {
    widthClasses = "w-48";
  }

  return (
    <>
      <Transition
        as={Fragment}
        show={open}
        enter="transition ease-out duration-200"
        enterFrom="opacity-0 scale-95"
        enterTo="opacity-100 scale-100"
        leave="transition ease-in duration-75"
        leaveFrom="opacity-100 scale-100"
        leaveTo="opacity-0 scale-95"
      >
        <div
          className={`absolute z-50 mt-2 rounded-md shadow-lg ${alignmentClasses} ${widthClasses}`}
          onClick={() => setOpen(false)}
        >
          <div className={`rounded-md ring-1 ring-black ring-opacity-5 ` + contentClasses}>
            {children}
          </div>
        </div>
      </Transition>
    </>
  );
};

const DropdownLink = ({ className = "", children, href, method, as, ...props }) => {
  // Nếu href bắt đầu bằng http nhưng khác origin hiện tại -> external
  const isExternal =
    href?.startsWith("http") && !href.startsWith(window.location.origin) && !method && !as;

  if (isExternal) {
    return (
      <a
        href={href}
        target="_blank"
        rel="noopener noreferrer"
        className={
          "block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 " +
          "hover:bg-gray-100 dark:hover:bg-neutral-800 focus:outline-none focus:bg-gray-100 " +
          "dark:focus:bg-neutral-800 transition duration-150 ease-in-out " +
          className
        }
        {...props}
      >
        {children}
      </a>
    );
  }

  return (
    <Link
      href={href}
      method={method}
      as={as}
      {...props}
      className={
        "block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 " +
        "hover:bg-gray-100 dark:hover:bg-neutral-800 focus:outline-none focus:bg-gray-100 " +
        "dark:focus:bg-neutral-800 transition duration-150 ease-in-out " +
        className
      }
    >
      {children}
    </Link>
  );
};

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
