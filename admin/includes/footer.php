   </div>
   </main>

   </div>


   <!-- SIDEBAR -->

   <div class="drawer-side z-50">
       <!-- Mobile overlay -->
       <label for="admin-drawer" aria-label="Close navigation" class="drawer-overlay"> </label>
       <aside
           class="admin-sidebar min-h-full bg-surface border-r border-line md:border md:rounded-2xl md:my-3 md:ml-3 md:h-[calc(100vh-24px)] md:min-h-0 shadow-card flex flex-col overflow-hidden">

           <!-- LOGO -->

           <div class="px-5 pt-5 pb-4 border-b border-line flex-shrink-0">
               <div class="flex items-start justify-between gap-3">
                   <a href="<?= ADMIN_URL ?>/index.php" class="block min-w-0">
                       <img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp" alt="<?= e(SITE_NAME) ?>" class="w-auto max-w-[130px] h-auto block">
                   </a>
                   <!-- Mobile close button -->
                   <label
                       for="admin-drawer"
                       aria-label="Close navigation"
                       class="md:hidden btn btn-sm btn-square btn-ghost text-muted hover:text-ink hover:bg-card2">
                       <svg
                           xmlns="http://www.w3.org/2000/svg"
                           class="h-5 w-5"
                           fill="none"
                           viewBox="0 0 24 24"
                           stroke="currentColor"
                           stroke-width="2">
                           <path
                               stroke-linecap="round"
                               stroke-linejoin="round"
                               d="M6 18L18 6M6 6l12 12" />
                       </svg>
                   </label>
               </div>
               <div class="flex items-center gap-1.5 mt-2">
                   <span
                       class="w-1.5 h-1.5
                     rounded-full
                     bg-accent">
                   </span>
                   <small
                       class="text-[10.5px]
                     font-semibold
                     text-muted
                     tracking-[1.2px]
                     uppercase">
                       Admin Panel
                   </small>
               </div>
           </div>

           <!--  NAVIGATION -->
           <nav
               class="py-4 px-3 flex-1 overflow-y-auto space-y-0.5">
               <!-- Overview -->
               <p class="px-2.5 pt-2 pb-1.5 text-[10.5px] font-bold text-muted tracking-[1.1px] uppercase">
                   Overview
               </p>

               <?php
                $isActive = basename($_SERVER['PHP_SELF']) === 'index.php';
                ?>

               <a
                   href="<?= ADMIN_URL ?>/index.php"
                   class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-colors
                <?= $isActive
                    ? 'bg-accent-soft text-accent-dark'
                    : 'text-ink2 hover:bg-card2 hover:text-ink'
                ?>
            ">
                   <svg class="h-[18px] w-[18px] flex-shrink-0
                    <?= $isActive
                        ? 'text-accent'
                        : 'text-muted group-hover:text-ink2'
                    ?>"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke="currentColor"
                       stroke-width="1.75">

                       <path
                           stroke-linecap="round"
                           stroke-linejoin="round"
                           d="M3.75 6.75A1.5 1.5 0 015.25 5.25h4.5A1.5 1.5 0 0111.25 6.75v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM12.75 6.75a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5v-4.5z" />

                   </svg>

                   Dashboard

               </a>


               <!-- Jobs -->
               <p
                   class="px-2.5
                   pt-4
                   pb-1.5
                   text-[10.5px]
                   font-bold
                   text-muted
                   tracking-[1.1px]
                   uppercase">

                   Jobs 1

               </p>


               <?php
                $isActive = basename($_SERVER['PHP_SELF']) === 'jobs.php';
                ?>

               <a
                   href="<?= ADMIN_URL ?>/pages/jobs.php"
                   class="
              group flex items-center gap-3
              px-2.5 py-2.5
              rounded-lg
              text-[13.5px]
              font-medium
              transition-colors

              <?= $isActive
                    ? 'bg-accent-soft text-accent-dark'
                    : 'text-ink2 hover:bg-card2 hover:text-ink'
                ?>
            ">

                   <svg
                       class="h-[18px] w-[18px] flex-shrink-0
              <?= $isActive
                    ? 'text-accent'
                    : 'text-muted group-hover:text-ink2'
                ?>"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke="currentColor"
                       stroke-width="1.75">

                       <path
                           stroke-linecap="round"
                           stroke-linejoin="round"
                           d="M9 6V4.5A1.5 1.5 0 0110.5 3h3A1.5 1.5 0 0115 4.5V6m-9 0h12a1.5 1.5 0 011.5 1.5v10.5a1.5 1.5 0 01-1.5 1.5H6a1.5 1.5 0 01-1.5-1.5V7.5A1.5 1.5 0 016 6z" />

                   </svg>

                   <span class="flex-1">
                       All Jobs
                   </span>

                   <?php
                    try {

                        $tc = db()
                            ->query("SELECT COUNT(*) FROM jobs WHERE status='published'")
                            ->fetchColumn();

                        if ($tc > 0) {

                            echo '
                <span
                  class="bg-accent
                         text-white
                         rounded-full
                         text-[10px]
                         font-bold
                         px-[7px]
                         py-px
                         leading-[16px]">'
                                . $tc .
                                '</span>';
                        }
                    } catch (Exception $e) {
                    }
                    ?>
               </a>

               <!-- Clients -->
               <?php
                $isActive = basename($_SERVER['PHP_SELF']) === 'clients.php';
                ?>
               <a href="<?= ADMIN_URL ?>/pages/clients.php" class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-colors <?= $isActive
                                                                                                                                                                            ? 'bg-accent-soft text-accent-dark'
                                                                                                                                                                            : 'text-ink2 hover:bg-card2 hover:text-ink'
                                                                                                                                                                        ?>
                ">
                   <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive
                                                                    ? 'text-accent'
                                                                    : 'text-muted group-hover:text-ink2'
                                                                ?>"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke="currentColor"
                       stroke-width="1.75">
                       <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21" />
                   </svg>
                   Clients
               </a>

               <!-- Post Job -->
               <?php
                $isActive = basename($_SERVER['PHP_SELF']) === 'post_job.php';
                ?>
               <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-colors <?= $isActive
                                                                                                                                                                            ? 'bg-accent-soft text-accent-dark'
                                                                                                                                                                            : 'text-ink2 hover:bg-card2 hover:text-ink'
                                                                                                                                                                        ?>
            ">
                   <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke="currentColor"
                       stroke-width="1.75">
                       <path
                           stroke-linecap="round"
                           stroke-linejoin="round"
                           d="M12 4.5v15m7.5-7.5h-15" />
                   </svg>
                   Post a Job
               </a>


               <!-- Drafts -->
               <a
                   href="<?= ADMIN_URL ?>/pages/jobs.php?status=draft"
                   class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[13.5px] font-medium text-ink2 hover:bg-card2 hover:text-ink transition-colors">
                   <svg class="h-[18px] w-[18px] flex-shrink-0 text-muted group-hover:text-ink2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                       <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25" />
                   </svg>
                   <span class="flex-1">
                       Drafts
                   </span>
                   <?php
                    try {
                        $dc = db()
                            ->query("SELECT COUNT(*) FROM jobs WHERE status='draft'")
                            ->fetchColumn();
                        if ($dc > 0) {
                            echo ' <span class="bg-warn text-white rounded-full text-[10px] font-bold px-[7px] py-px leading-[16px]">' . $dc . '</span>';
                        }
                    } catch (Exception $e) {
                    }
                    ?>
               </a>

               <!-- Settings -->
               <p class="px-2.5 pt-4 pb-1.5 text-[10.5px] font-bold text-muted tracking-[1.1px] uppercase"> Settings </p>

               <!-- Admin Users -->
               <?php
                $isActive = basename($_SERVER['PHP_SELF']) === 'admins.php';
                ?>
               <a href="<?= ADMIN_URL ?>/pages/admins.php" class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-colors <?= $isActive
                                                                                                                                                                            ? 'bg-accent-soft text-accent-dark'
                                                                                                                                                                            : 'text-ink2 hover:bg-card2 hover:text-ink'
                                                                                                                                                                        ?>
            ">
                   <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive
                                                                    ? 'text-accent'
                                                                    : 'text-muted group-hover:text-ink2'
                                                                ?>"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke="currentColor"
                       stroke-width="1.75">
                       <path
                           stroke-linecap="round"
                           stroke-linejoin="round"
                           d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                   </svg>
                   Admin Users
               </a>
           </nav>

           <!-- CURRENT ADMIN -->
           <div class="px-3.5 py-3.5 border-t border-line flex-shrink-0">
               <div class="flex items-center gap-2.5 px-1.5 py-2 rounded-lg">
                   <div class="w-8 h-8 rounded-full bg-accent flex items-center justify-center font-head text-[13px] font-bold text-white flex-shrink-0">
                       <?= strtoupper(substr($currentAdmin['name'], 0, 1)) ?>
                   </div>
                   <div class="min-w-0 flex-1">
                       <strong class="block text-[13px] text-ink font-semibold truncate">
                           <?= e($currentAdmin['name']) ?>
                       </strong>
                       <span class="block text-[11px] text-ink2 capitalize truncate">
                           <?= e($currentAdmin['role']) ?>
                       </span>
                   </div>
                   <div class="tooltip" data-tip="Sign out">
                       <a href="<?= ADMIN_URL ?>/logout.php" title="Sign out" class="flex-shrink-0 p-1.5 rounded-md text-muted hover:text-danger hover:bg-danger/[.06] transition-colors">
                           <svg class="h-[17px] w-[17px]" fill="none"
                               viewBox="0 0 24 24"
                               stroke="currentColor"
                               stroke-width="1.75">
                               <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15" />
                           </svg>
                       </a>
                   </div>
               </div>
           </div>
       </aside>
   </div>
   </div>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
   <script>
       // Sidebar toggle for mobile
       const sidebarToggle = document.getElementById('sidebarToggle');
       const sidebar = document.getElementById('sidebar');
       const sidebarOverlay = document.getElementById('sidebarOverlay');

       function setSidebarOpen(isOpen) {
           if (typeof setAdminSidebarOpen === 'function') {
               setAdminSidebarOpen(isOpen);
           }
       }

       sidebarToggle?.setAttribute('aria-expanded', 'false');
       sidebarOverlay?.addEventListener('click', () => {
           setSidebarOpen(false);
       });

       // Nav group accordion
       function toggleNavGroup(groupId, button) {
           const panel = document.getElementById(groupId);
           const chevron = document.getElementById(groupId + 'Chevron');

           panel.classList.toggle('closed');
           chevron.classList.toggle('open');
       }

       // Account menu toggle
       function toggleAccountMenu() {
           const menu = document.getElementById('accountMenu');
           const chevron = document.getElementById('accountChevron');

           menu.classList.toggle('closed');
           chevron.classList.toggle('rotate-180');
       }

       // Close account menu when clicking outside
       document.addEventListener('click', (e) => {
           const menu = document.getElementById('accountMenu');
           const button = e.target.closest('button[onclick="toggleAccountMenu()"]');

           if (!button && !menu?.contains(e.target)) {
               menu?.classList.add('closed');
               document.getElementById('accountChevron')?.classList.remove('rotate-180');
           }
       });

       function openSidebar() {
           setSidebarOpen(true);
       }

       function closeSidebar() {
           setSidebarOpen(false);
       }

       document.addEventListener('keydown', function(event) {
           if (event.key === 'Escape') {
               closeSidebar();
           }
       });

       window.addEventListener('resize', function() {
           if (window.innerWidth >= 768) {
               closeSidebar();
           }
       });
   </script>
   </body>

   </html>