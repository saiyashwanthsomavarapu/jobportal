
</div><!-- /main-body -->
</div><!-- /main -->
</div><!-- /admin-layout -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded',function(){
  const sb = document.getElementById('sidebar');
  if(sb){
    // Add hamburger to topbar on mobile (injected via JS)
    const tb = document.querySelector('.topbar');
    if(tb && window.innerWidth < 768){
      const ham = document.createElement('button');
      ham.innerHTML = '☰';
      ham.style.cssText = 'background:none;border:none;color:var(--text);font-size:20px;cursor:pointer;margin-right:12px';
      ham.onclick = () => sb.classList.toggle('open');
      tb.insertBefore(ham, tb.firstChild);
    }
  }
});
</script>
</body>
</html>
