<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Contact, Route } from '@lucide/vue';
import { computed } from 'vue';
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
import contactos from '@/routes/modulos/contactos';
import rutas from '@/routes/modulos/rutas';
import type { NavItem } from '@/types';

const page = usePage();

// La cuenta compartida "tecavi@segurtrack.com" (rol "usuario") ve Rutas y
// Contactos igual que el admin (ambas `role:admin,usuario` en el backend) --
// solo se le oculta su propio nombre en el footer, más abajo.
const esUsuarioLimitado = computed(() =>
    page.props.auth.roles.includes('usuario'),
);

const mainNavItems: NavItem[] = [
    { title: 'Rutas', href: rutas.index(), icon: Route },
    { title: 'Contactos', href: contactos.index(), icon: Contact },
];

// Pedido explícito: esta cuenta no debe ver su propio nombre/correo en el
// sidebar. Sin sesión tampoco hay nada que mostrar ahí.
const mostrarNavUser = computed(
    () => page.props.auth.user !== null && !esUsuarioLimitado.value,
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="px-2 py-1.5 group-data-[collapsible=icon]:py-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="h-13 p-0!" as-child>
                        <Link :href="rutas.index()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter v-if="mostrarNavUser">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
