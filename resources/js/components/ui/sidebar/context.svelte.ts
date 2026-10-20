import { getContext, setContext } from "svelte";
import { SIDEBAR_KEYBOARD_SHORTCUT } from "./constants";

export class IsMobile {
    #current = $state(false);

    constructor(breakpoint: number = 768) {
        if (typeof window !== "undefined") {
            const mql = window.matchMedia(`(max-width: ${breakpoint - 1}px)`);
            this.#current = mql.matches;
            mql.addEventListener("change", (e) => {
                this.#current = e.matches;
            });
        }
    }

    get current() {
        return this.#current;
    }
}

type Getter<T> = () => T;

export type SidebarStateProps = {
    open: Getter<boolean>;
    setOpen: (open: boolean) => void;
};

export class SidebarState {
    readonly props: SidebarStateProps;
    open = $derived.by(() => this.props.open());
    openMobile = $state(false);
    setOpen: SidebarStateProps["setOpen"];
    #isMobile: IsMobile;
    state = $derived.by(() => (this.open ? "expanded" : "collapsed"));

    constructor(props: SidebarStateProps) {
        this.setOpen = props.setOpen;
        this.#isMobile = new IsMobile();
        this.props = props;
    }

    get isMobile() {
        return this.#isMobile.current;
    }

    handleShortcutKeydown = (e: KeyboardEvent) => {
        if (e.key === SIDEBAR_KEYBOARD_SHORTCUT && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            this.toggle();
        }
    };

    setOpenMobile = (value: boolean) => {
        this.openMobile = value;
    };

    toggle = () => {
        return this.isMobile ? (this.openMobile = !this.openMobile) : this.setOpen(!this.open);
    };
}

const SYMBOL_KEY = "tp-sidebar";

export function setSidebar(props: SidebarStateProps): SidebarState {
    return setContext(Symbol.for(SYMBOL_KEY), new SidebarState(props));
}

export function useSidebar(): SidebarState {
    return getContext(Symbol.for(SYMBOL_KEY));
}
