jQuery (function() {
     var selector = 'a.urlextern';
     if(typeof JSINFO !== 'undefined' && 'linkcheck_selector' in JSINFO && JSINFO['linkcheck_selector']){
          selector=JSINFO['linkcheck_selector'];
     }
     jQuery(selector).each (function( index ) { 
     var lnk = jQuery( this );
     var url  = jQuery( this ).attr('href');
     var dbg=0;

     if(!url || !url.match(/^https?:\/\//)&&!url.match(/^\/\//)){
          //if(dbg) console.log('Skipping '+url+' because it does not appear to be an external http(s) url.');
          return;
     } 
     if(typeof LINKCHECKDATA !== 'undefined' && url in LINKCHECKDATA) {
          if(dbg) console.log('Found in LINKCHECKDATA: '+url+' -> '+LINKCHECKDATA[url]);
          lnk.removeClass('urlextern').addClass("linkcheck_"+LINKCHECKDATA[url]);
          return;
     }
     
     if(!lnk.hasClass('linkcheck')){
          if(dbg){
               if(lnk.attr('class') && lnk.attr('class').match(/linkcheck_/)) console.log('Skipping '+url+' because it has a static class');
               else console.log('Skipping '+url+' because it has no linkcheck class. linkcheck plugin may be off globally or turned of for this page with {{linkcheck>off}}.');
          }
          return;
     }
     
     var request = jQuery.ajax({
          url: DOKU_BASE + 'lib/exe/ajax.php',
          type: 'POST',
          data: { 
               call: 'linkcheck',
               url: url
          },
          dataType: "json"
     });
          
     jQuery.when(request).done(function(data,status) {
          if(dbg) console.log('Got ajax codegroup for '+url+' -> '+data['codegroup']);
          lnk.removeClass('urlextern').addClass("linkcheck_"+data['codegroup']);
          if(data['msg']!='')
               lnk.attr('title',lnk.attr('title')+': '+data['msg']);
     });
}); });