<template>
  <div>
    <!-- <navbar /> -->
    <main>
      <section class="relative w-full h-full py-40 min-h-screen">
        <div
          class="absolute top-0 w-full h-full bg-blueGray-800 bg-no-repeat bg-full"
          :style="`background-image: url('${registerBg2}');`"
        ></div>
        <ClientPick
          :initial-view="initialView"
          :initial-code="initialCode"
          :auto-open="autoOpen"
        />
        <footer-small absolute />
      </section>
    </main>
  </div>
</template>
<script setup>
    import Navbar from "@/Components/Navbars/AuthNavbar.vue";
    import FooterSmall from "@/Components/Footers/FooterSmall.vue";
    import ClientPick from "@/Pages/TicketsV2/ClientComponents/ClientPick.vue"

    import registerBg2 from "@/assets/img/register_bg_2.png";

    defineProps({
        initialView: { type: String, default: 'create' },
        initialCode: { type: String, default: null },
        autoOpen: { type: Boolean, default: false },
    })
</script>
