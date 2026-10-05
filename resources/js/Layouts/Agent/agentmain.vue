<script>
import { layoutMethods,layoutComputed } from "@/state/helpers";

import AgentHorizontal from "./agentHorizontal.vue";
import AgentVertical from "./agentVertical.vue";

export default {
    components: {
        AgentHorizontal,
        AgentVertical
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
        <AgentHorizontal v-if="layoutType === 'horizontal'" :layout="layoutType">
            <slot />
        </AgentHorizontal>
         <AgentVertical v-if="layoutType === 'vertical' || layoutType === 'semibox'" :layout="layoutType">
            <slot />
        </AgentVertical>

    </div>
</template>
