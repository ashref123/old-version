<script>
import { layoutMethods,layoutComputed } from "@/state/helpers";

import DispatchHorizontal from "./dispatchProHorizontal.vue";
import DispatchVertical from "./dispatchProVertical.vue";

export default {
    components: {
        DispatchHorizontal,
        DispatchVertical
    },
    methods: {
        ...layoutMethods
    },
    created: () => {
        document.documentElement.setAttribute("data-layout", "horizontal");
    },
    data() {
        return {};
    },
    computed: {
        ...layoutComputed,
    },
    mounted() {
    }
};
</script>

<template>
    <div>
        <DispatchHorizontal v-if="layoutType === 'horizontal'" :layout="layoutType">
            <slot />
        </DispatchHorizontal>
         <DispatchVertical v-if="layoutType === 'vertical' || layoutType === 'semibox'" :layout="layoutType">
            <slot />
        </DispatchVertical>

    </div>
</template>
