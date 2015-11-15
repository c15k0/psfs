/*
 Angular-Paging v2.0.0 by Brant Wills - MIT licensed
 https://github.com/brantwills/Angular-Paging.git
 */
angular.module("bw.paging",[]).directive("paging",function(){function a(a,b,c){a.$watchCollection("[page,pageSize,total]",function(){j(a,c)})}function b(a,b){a.List=[],a.Hide=!1,a.dots=a.dots||"...",a.page=parseInt(a.page)||1,a.total=parseInt(a.total)||0,a.ulClass=a.ulClass||"pagination",a.adjacent=parseInt(a.adjacent)||2,a.activeClass=a.activeClass||"active",a.disabledClass=a.disabledClass||"disabled",a.scrollTop=a.$eval(b.scrollTop),a.hideIfEmpty=a.$eval(b.hideIfEmpty),a.showPrevNext=a.$eval(b.showPrevNext)}function c(a,b){a.page>b&&(a.page=b),a.page<=0&&(a.page=1),a.adjacent<=0&&(a.adjacent=2),1>=b&&(a.Hide=a.hideIfEmpty)}function d(a,b){a.page!=b&&(a.page=b,a.pagingAction({page:a.page,pageSize:a.pageSize,total:a.total}),a.scrollTop&&scrollTo(0,0))}function e(a,b,c){if(a.showPrevNext&&!(1>b)){var e,f,g;if("prev"===c){e=a.page-1<=0;var h=a.page-1<=0?1:a.page-1;f={value:"<<",title:"First Page",page:1},g={value:"<",title:"Previous Page",page:h}}else{e=a.page+1>b;var i=a.page+1>=b?b:a.page+1;f={value:">",title:"Next Page",page:i},g={value:">>",title:"Last Page",page:b}}var j=function(b,c){a.List.push({value:b.value,title:b.title,liClass:c?a.disabledClass:"",action:function(){c||d(a,b.page)}})};j(f,e),j(g,e)}}function f(a,b,c){var e=0;for(e=a;b>=e;e++){var f={value:e,title:"Page "+e,liClass:c.page==e?c.activeClass:"",action:function(){d(c,this.value)}};c.List.push(f)}}function g(a){a.List.push({value:a.dots})}function h(a,b){f(1,2,a),3!=b&&g(a)}function i(a,b,c){c!=a-2&&g(b),f(a-1,a,b)}function j(a,d){(!a.pageSize||a.pageSize<=0)&&(a.pageSize=1);var g=Math.ceil(a.total/a.pageSize);b(a,d),c(a,g);var j,k,l=2*a.adjacent+2;e(a,g,"prev"),l+2>=g?(j=1,f(j,g,a)):a.page-a.adjacent<=2?(j=1,k=1+l,f(j,k,a),i(g,a,k)):a.page<g-(a.adjacent+2)?(j=a.page-a.adjacent,k=a.page+a.adjacent,h(a,j),f(j,k,a),i(g,a,k)):(j=g-l,k=g,h(a,j),f(j,k,a)),e(a,g,"next")}return{restrict:"EA",link:a,scope:{page:"=",pageSize:"=",total:"=",dots:"@",hideIfEmpty:"@",ulClass:"@",activeClass:"@",disabledClass:"@",adjacent:"@",scrollTop:"@",showPrevNext:"@",pagingAction:"&"},template:'<ul ng-hide="Hide" ng-class="ulClass"> <li title="{{Item.title}}" ng-class="Item.liClass" ng-click="Item.action()" ng-repeat="Item in List"> <span ng-bind="Item.value"></span> </li></ul>'}});