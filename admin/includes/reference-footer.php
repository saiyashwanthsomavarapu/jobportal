  </div></main>
  <script>const shellMenu=document.getElementById('mobileMenu'),shellSidebar=document.getElementById('dashboardSidebar'),shellOverlay=document.getElementById('sidebarOverlay');function setShellMenu(open){shellSidebar.classList.toggle('open',open);shellOverlay.classList.toggle('open',open);shellMenu.setAttribute('aria-expanded',String(open))}shellMenu.addEventListener('click',()=>setShellMenu(!shellSidebar.classList.contains('open')));shellOverlay.addEventListener('click',()=>setShellMenu(false));</script>
</body></html>
