(()=>{var e={92703:(e,r,t)=>{"use strict";var n=t(50414);function o(){}function i(){}i.resetWarningCache=o,e.exports=function(){function e(e,r,t,o,i,a){if(a!==n){var c=new Error("Calling PropTypes validators directly is not supported by the `prop-types` package. Use PropTypes.checkPropTypes() to call them. Read more at http://fb.me/use-check-prop-types");throw c.name="Invariant Violation",c}}function r(){return e}e.isRequired=e;var t={array:e,bigint:e,bool:e,func:e,number:e,object:e,string:e,symbol:e,any:e,arrayOf:r,element:e,elementType:e,instanceOf:r,node:e,objectOf:r,oneOf:r,oneOfType:r,shape:r,exact:r,checkPropTypes:i,resetWarningCache:o};return t.PropTypes=t,t}},45697:(e,r,t)=>{e.exports=t(92703)()},50414:e=>{"use strict";e.exports="SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED"}},r={};function t(n){var o=r[n];if(void 0!==o)return o.exports;var i=r[n]={exports:{}};return e[n](i,i.exports,t),i.exports}t.n=e=>{var r=e&&e.__esModule?()=>e.default:()=>e;return t.d(r,{a:r}),r},t.d=(e,r)=>{for(var n in r)t.o(r,n)&&!t.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:r[n]})},t.o=(e,r)=>Object.prototype.hasOwnProperty.call(e,r),(()=>{"use strict";const e=window.wp.element;function r(e,r){(null==r||r>e.length)&&(r=e.length);for(var t=0,n=new Array(r);t<r;t++)n[t]=e[t];return n}function n(e,t){return function(e){if(Array.isArray(e))return e}(e)||function(e,r){var t=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=t){var n,o,i,a,c=[],l=!0,s=!1;try{if(i=(t=t.call(e)).next,0===r){if(Object(t)!==t)return;l=!1}else for(;!(l=(n=i.call(t)).done)&&(c.push(n.value),c.length!==r);l=!0);}catch(e){s=!0,o=e}finally{try{if(!l&&null!=t.return&&(a=t.return(),Object(a)!==a))return}finally{if(s)throw o}}return c}}(e,t)||function(e,t){if(e){if("string"==typeof e)return r(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);return"Object"===n&&e.constructor&&(n=e.constructor.name),"Map"===n||"Set"===n?Array.from(e):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?r(e,t):void 0}}(e,t)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}const o=window.React,i=window.wp.components;var a=t(45697),c=t.n(a);function l(r){var t=r.formID,a=r.homeUrl,c=r.previewToken,l=r.isBlock,s=void 0===l||l,u=(0,e.useId)(),f=(0,e.useRef)(),p=n((0,e.useState)(!0),2),m=p[0],d=p[1],y=n((0,e.useState)(0),2),h=y[0],v=y[1],w=n((0,e.useState)("auto"),2),g=w[0],b=w[1];return(0,o.createElement)("div",{id:u,className:"nf-iframe-container"},(0,o.createElement)("div",{className:"nf-iframe-overlay",style:s?{}:{display:"flex",width:"100%",height:"100%",flexDirection:"column",overflow:"hidden"}},m&&(0,o.createElement)(i.Spinner,null),(0,o.createElement)("iframe",{src:"".concat(a,"?nf_preview_form=").concat(t,"&nf_iframe=").concat(c),title:"nf-preview-form-".concat(t),ref:function(e){return f.current=e},onLoad:function(){d(!1);var e=f.current.contentWindow.document.getElementById("nf-form-".concat(t,"-cont")),r=e.querySelectorAll(".ninja-forms-form-wrap");r&&r.length?(b(r[0].scrollWidth),v(r[0].scrollHeight)):(b(e.scrollWidth),v(e.scrollHeight))},scrolling:"no",height:s&&h?h:"",width:s&&g?g:"",style:{pointerEvents:"none",flexGrow:"1",border:"none",margin:"0",padding:"0"}})))}l.propTypes={formID:c().number.isRequired,homeUrl:c().string.isRequired,previewToken:c().string.isRequired};var s=ninja_forms_form_iframe_data||{},u=s.formID,f=s.homeUrl,p=s.previewToken,m=s.isBlock,d="nf_form_iframe_"+u,y=document.getElementById(d),h=d+"_"+(Date.now().toString(36)+Math.random().toString(36));y.setAttribute("id",h);var v=(0,e.createElement)(l,{formID:u,homeUrl:f,previewToken:p,isBlock:m});(0,e.createRoot)(y).render(v)})()})();