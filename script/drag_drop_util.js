if (typeof UO == "undefined" || !UO) { var UO = {}; }
UO.DDUtil =
	function(dragData) {
		var Dom = YAHOO.util.Dom;
		var Event = YAHOO.util.Event;
		var DDM = YAHOO.util.DragDropMgr;

		YAHOO.example.UODragHandler = {
			init: function(e, unknown, dragData) {
				for (target of dragData.targets) {
					new YAHOO.util.DDTarget(target);
				}
				for (item of dragData.items) {
					new YAHOO.example.DDList(item.id, item.hId, item.parent, item.itemClass, dragData);
				}
			}
		}

		YAHOO.example.DDList = function(id, handleId, parentId, itemClass, dragData, sGroup) {

			YAHOO.example.DDList.superclass.constructor.call(this, id, sGroup);

			this.logger = this.logger || YAHOO;
			var el = this.getDragEl();
			Dom.setStyle(el, "opacity", 0.57); // The proxy is slightly transparent
			this.goingUp = false;
			this.lastY = 0;

			this.setHandleElId(handleId);
			this.parent = parentId;
			this.itemClass = itemClass;
			this.parentClass = dragData.parentClass;
			this.eventHandler = dragData.handler;
		};

		YAHOO.extend(YAHOO.example.DDList, YAHOO.util.DDProxy, {

			startDrag: function(x, y) {
				// make the proxy look like the source element
				var dragEl = this.getDragEl();
				var clickEl = this.getEl();
				Dom.setStyle(clickEl, "visibility", "hidden");

				dragEl.innerHTML = clickEl.innerHTML;

				Dom.setStyle(dragEl, "color", Dom.getStyle(clickEl, "color"));
				Dom.setStyle(dragEl, "backgroundColor", Dom.getStyle(clickEl, "backgroundColor"));
				Dom.setStyle(dragEl, "font-size", Dom.getStyle(clickEl, "font-size"));
				Dom.setStyle(dragEl, "font-family", Dom.getStyle(clickEl, "font-family"));
				Dom.setStyle(dragEl, "border", "2px solid gray");
				this.eventHandler.onStartDrag(dragEl, clickEl);
			},

			endDrag: function(e) {

				var srcEl = this.getEl();
				var proxy = this.getDragEl();

				// Show the proxy element and animate it to the src element's location
				Dom.setStyle(proxy, "visibility", "");
				var a = new YAHOO.util.Motion(
					proxy, {
					points: {
						to: Dom.getXY(srcEl)
					}
				},
					0.2,
					YAHOO.util.Easing.easeOut
				)
				var proxyid = proxy.id;
				var thisid = this.id;

				// Hide the proxy and show the source element when finished with the animation
				a.onComplete.subscribe(function() {
					Dom.setStyle(proxyid, "visibility", "hidden");
					Dom.setStyle(thisid, "visibility", "");
				});
				a.animate();
				this.eventHandler.onEndDrag(srcEl, proxy, this.parent);
			},

			onDragDrop: function(e, id) {

				// If there is one drop interaction, the item was dropped either on the list,
				// or it was dropped on the current location of the source element.
				if (DDM.interactionInfo.drop.length === 1) {

					// The position of the cursor at the time of the drop (YAHOO.util.Point)
					var pt = DDM.interactionInfo.point;

					// The region occupied by the source element at the time of the drop
					var region = DDM.interactionInfo.sourceRegion;

					// Check to see if we are over the source element's location.  We will
					// append to the bottom of the list once we are sure it was a drop in
					// the negative space (the area of the list without any list items)
					var parent = null
					if (!region.intersect(pt)) {
						var destEl = Dom.get(id);
						var destDD = DDM.getDDById(id);
						if (destEl.className == this.parentClass)
							parent = destEl;
						else
							parent = destEl.getElementsByClassName(this.parentClass)[0]
					    var placeholder = parent.getElementsByClassName('placeholder');
						if (placeholder.length > 0)
							parent.insertBefore(this.getEl(), placeholder[0].parentNode);
						else						
							parent.appendChild(this.getEl());
						destDD.isEmpty = false;
						DDM.refreshCache();
					}
					this.eventHandler.onDragDrop(Dom.get(id), parent);

				}
			},

			onDrag: function(e) {

				// Keep track of the direction of the drag for use during onDragOver
				var y = Event.getPageY(e);

				if (y < this.lastY) {
					this.goingUp = true;
				} else if (y > this.lastY) {
					this.goingUp = false;
				}

				this.lastY = y;
			},

			onDragOver: function(e, id) {

				var srcEl = this.getEl();
				var destEl = Dom.get(id);

				// We are only concerned with list items, we ignore the dragover
				// notifications for the list.
				if (destEl.className == this.itemClass) {
					var orig_p = srcEl.parentNode;
					var p = destEl.parentNode;

					if (this.goingUp) {
						p.insertBefore(srcEl, destEl); // insert above
					} else {
						p.insertBefore(srcEl, destEl.nextSibling); // insert below
					}
					this.eventHandler.onDragOver(destEl, this.getDragEl(), this.goingUp);

					DDM.refreshCache();
				}
			}
		});

		Event.onDOMReady(YAHOO.example.UODragHandler.init, dragData, true);

	};
