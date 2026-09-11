<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Contact, FlaskConical, LayoutGrid, Route } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import contactos from '@/routes/modulos/contactos';
import rutas from '@/routes/modulos/rutas';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Rutas',
        href: rutas.index(),
        icon: Route,
    },
    {
        title: 'Rutas · Pruebas',
        href: rutas.gestion(),
        icon: FlaskConical,
    },
    {
        title: 'Contactos',
        href: contactos.index(),
        icon: Contact,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="px-2 py-1.5 group-data-[collapsible=icon]:py-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="h-13 p-0!" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
