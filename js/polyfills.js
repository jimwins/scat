/*
    Copyright 2018  Alfredo Mungo <alfredo.mungo@protonmail.ch>

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to
    deal in the Software without restriction, including without limitation the
    rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
    sell copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
    IN THE SOFTWARE.
*/
if (!Object.fromEntries) {
  Object.defineProperty(Object, 'fromEntries', {
    value(entries) {
      if (!entries || !entries[Symbol.iterator]) { throw new Error('Object.fromEntries() requires a single iterable argument'); }

      return [...entries].reduce((obj, [key, val]) => {
        obj[key]= val
        return obj
      }, {})
    },
  });
}

/**
* replaceChildren.js
* @mdn https://developer.mozilla.org/en-US/docs/Web/API/ParentNode/replaceChildren
* @email yanwenbin1991@live.com
**/
(function (item) {
    if (!item.replaceChildren) {
        item.replaceChildren = function () {
            var parentNode = this;
            var oldNodes = [].slice.call(parentNode.childNodes);
            var newNodes = [].slice.call(arguments);
            oldNodes.forEach(function (nodes) {
                if (!newNodes.find(function(el){ return el === nodes})) {
                    parentNode.removeChild(nodes)
                }
            });
            parentNode.append.apply(this,newNodes);
        }
    }
})(HTMLElement.prototype);
