/**
 * imageHandler.js
 *
 * @package imageHandler
 * @subpackage test
 * @copyright 2015, Kjell-Inge Gustafsson kigkonsult, All rights reserved
 * @author    Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @link      http://kigkonsult.se/imageHandler/index.php
 * @license   non-commercial use: Creative Commons
 *            Attribution-NonCommercial-NoDerivatives 4.0 International License
 *            (http://creativecommons.org/licenses/by-nc-nd/4.0/)
 *            commercial use :imageHandler141license / imageHandler14Xlicense
 * @version   1.4
 */
var chrs = ['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
            'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'],
            telems=['w','h','mw','mh','cw','ch','cx','cy'],tlen=telems.length
function cghLog(e){
  var v=e.value, e=document.getElementById('logFile'),ec=('-1'== v) ? 'grey' : '';
  e.className = ec;
}
function randomString(len) {
  len = (isNaN(len)) ? 22 : len;
  var tmp, current, top = chrs.length, randomStr = '',i;
  if(top) {
    while(--top) {
      current = Math.floor(Math.random() * (top + 1));
      tmp = chrs[current];
      chrs[current] = chrs[top];
      chrs[top] = tmp;
    }
  }
  for(i=0;i<len;i++)
    randomStr += chrs[Math.floor(Math.random()*chrs.length)];
  return randomStr;
}
function setRow(fx,tno) {
  var e;
  if(null==testcases[tno])
    tno = 0;
  else if(testcases.length <= tno)
    tno = 0;
  if(tno!=document.getElementById('TestSelect'+fx).selectedIndex)
    document.getElementById('TestSelect'+fx).selectedIndex = tno;
  document.getElementById('input'+fx).innerHTML  = testcases[tno]['input'];
  document.getElementById('output'+fx).innerHTML = testcases[tno]['result'];
  for(var i=0;i<tlen;i++) {
    e = document.getElementById(telems[i]+fx);
    if(null!=e)
      document.getElementById(telems[i]+fx).value  = testcases[tno][telems[i]];
  }
}
function submitForm(f,fx,o,skip) {
  var form=document.getElementById(f),e=document.getElementById('operation'+fx),i,n;
  skip   = skip||false;
  if(null==e)
    return false;
  if('save'==o) {
    e.value=o;
    form.submit();
    return false;
  }
  url    = js2Url+'&o=';
  url   += ('stream'==o) ? 2 : 1;
  url   += '&i=' + form.elements['loadDirectory'].value;
  if(''>=form.elements['i'].value)
    return false;
  url   += form.elements['i'].value;
  if(!skip) {
    for(i=0;i<tlen;i++) {
      e  = form.elements[telems[i]+fx];
      if((null!=e)&&(''<e.value))
        url += '&' + telems[i] + '=' + e.value;
    }
    n = Math.floor((Math.random() * 3) + 1);
    switch(n) { // testing org., anonymous and (imageHandler) generated filename
      case 1 : n = form.elements['i'].value; break;
      case 2 : n = 'test'+randomString(6); break;
      case 3 : n = ''; break;
    }
    url += '&n=' + n;
  }
  window.open(url, '_blank');
}
function test(f,fx,tno) {
  tno=parseInt(tno,10);
  if(isNaN(tno))
    tno = 0;
  setRow(fx,tno);
  submitForm(f,fx,'download');
}
function testAdd(f,fx) {
  var s=document.getElementById('TestSelect'+fx),tno=parseInt(s.value,10);
  if(isNaN(tno))
    tno = 0;
  tno += 1;
  if(testcases.length < tno)
    tno = 0;
  s.selectedIndex = tno;
  test(f,fx,tno);
}
function testSub(f,fx) {
  var s=document.getElementById('TestSelect'+fx),tno=parseInt(s.value,10);
  if(isNaN(tno))
    tno = 0;
  tno -= 1;
  if(0 > tno)
    tno = (testcases.length - 1);
//alert('testSub, tno='+tno); // test ###
  s.selectedIndex = tno;
  test(f,fx,tno);
}
function toogleElement(id,show) {
  show=show||false;
  var e=(typeof id == 'string') ? document.getElementById(id) : id;
  if(('none'==e.style.display)||show)
    toogleElementShow(e);
  else
    toogleElementHide(e);
}
function toogleElementHide(e) {
  e.style.display='none';
}
function toogleElementShow(e) {
  switch(e.nodeName) {
    case 'DIV':
      e.style.display='block';
      break;
    case 'INPUT':
    case 'SPAN':
      e.style.display='inline-block';
      break;
    default:
      e.style.display='table-row-group';
      break;
  }
}
