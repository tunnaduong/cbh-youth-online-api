import React, { useState } from "react";
import { usePopper } from "react-popper";

export default function Tooltip({ children, content, placement = "bottom" }) {
    const [referenceElement, setReferenceElement] = useState(null);
    const [popperElement, setPopperElement] = useState(null);
    const [arrowElement, setArrowElement] = useState(null);
    const [showTooltip, setShowTooltip] = useState(false);

    const { styles, attributes } = usePopper(referenceElement, popperElement, {
        placement,
        modifiers: [
            { name: "arrow", options: { element: arrowElement } },
            { name: "offset", options: { offset: [0, 8] } },
        ],
    });

    return (
        <>
            <div
                ref={setReferenceElement}
                onMouseEnter={() => setShowTooltip(true)}
                onMouseLeave={() => setShowTooltip(false)}
                className="inline-block"
            >
                {children}
            </div>
            {showTooltip && (
                <div
                    ref={setPopperElement}
                    style={styles.popper}
                    {...attributes.popper}
                    className="bg-neutral-800 text-white px-2 py-1 rounded text-sm z-50 w-48 text-center"
                >
                    {content}
                    <div ref={setArrowElement} style={styles.arrow} />
                </div>
            )}
        </>
    );
}
